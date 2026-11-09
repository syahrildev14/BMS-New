<?php

namespace App\Http\Controllers\Dapur;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Jobs\GenerateTandaTerimaPdfJob;
use App\Models\Dapur;
use App\Models\DeliveryDocumentPhoto;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SuratJalan;
use App\Services\DeliveryNoteService;
use App\Services\DeliveryVerificationService;
use App\Services\RevisionGateService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class OrderController extends Controller
{
    private const ITEM_SHIPPED_MESSAGE = 'Item ini sudah punya Surat Jalan dan tidak bisa diedit lagi.';

    public function index(Request $request): Response
    {
        $dapurId = $request->user()->dapur_id;

        return Inertia::render('dapur/orders/index', [
            'orders' => Order::where('dapur_id', $dapurId)
                ->latest('delivery_date')
                ->with('orderItems:id,order_id')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('dapur/orders/create', [
            'items' => $this->orderableItems(),
        ]);
    }

    /**
     * Catalog for Dapur forms: only what they choose from. Cost, margin and
     * supplier are the koperasi's and must not reach the Dapur's browser.
     */
    private function orderableItems(): Collection
    {
        return Item::with('satuan:id,name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'satuan_id']);
    }

    public function store(StoreOrderRequest $request, RevisionGateService $revisionGate): RedirectResponse
    {
        $dapurId = $request->user()->dapur_id;
        $validated = $request->validated();

        // Retried on deadlock: two first submissions for one Dapur+date can
        // both hold the gap lock from the existing-Order lookup before inserting.
        $result = DB::transaction(function () use ($dapurId, $validated, $revisionGate, $request) {
            $order = $this->findOrCreateOrderSafely($dapurId, $validated);

            if (in_array($order->status, ['CANCELLED', 'COMPLETED'], true)) {
                throw ValidationException::withMessages([
                    'delivery_date' => "Order untuk tanggal ini sudah {$order->status}, tidak bisa ditambah. Hubungi koperasi.",
                ]);
            }

            return $revisionGate->submitRevision($order, $this->resolveOrderLines($validated['items']), $request->user());
        }, 3);

        return redirect()
            ->route('dapur.orders.index')
            ->with('success', "Order berhasil dikirim (tier: {$result['tier']}).");
    }

    public function show(Request $request, Order $order): Response
    {
        abort_unless($order->dapur_id === $request->user()->dapur_id, 403);

        $order->load([
            'orderItems.item:id,name,satuan_id',
            'suratJalans' => fn ($query) => $query->with(['supplier:id,name', 'photos'])->orderBy('surat_jalans.id'),
        ]);
        $order->orderItems->makeHidden('final_cost_price');

        return Inertia::render('dapur/orders/show', [
            'order' => $order,
            'items' => $this->orderableItems(),
        ]);
    }

    /**
     * Dapur records the physical STT (Surat Tanda Terima) result —
     * qty_received/qty_rejected — for order_items that already have a Surat
     * Jalan attached. This is what Finance's Reconciled Split Billing (§Module 4)
     * reads: whereNotNull('qty_received') is how an item becomes billable.
     */
    public function submitStt(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->dapur_id === $request->user()->dapur_id, 403);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:order_items,id'],
            'items.*.qty_received' => [
                'required', 'numeric', 'min:0',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    $index = (int) explode('.', $attribute)[1];
                    $orderItem = OrderItem::find($request->input("items.{$index}.id"));

                    if ($orderItem && (float) $value > (float) $orderItem->qty_ordered) {
                        $fail("qty_received ({$value}) tidak boleh melebihi qty_ordered ({$orderItem->qty_ordered}).");
                    }
                },
            ],
            'items.*.qty_rejected' => [
                'required', 'numeric', 'min:0',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    $index = (int) explode('.', $attribute)[1];
                    $qtyReceived = (float) $request->input("items.{$index}.qty_received");

                    if ((float) $value > $qtyReceived) {
                        $fail("qty_rejected ({$value}) tidak boleh melebihi qty_received ({$qtyReceived}).");
                    }
                },
            ],
            'items.*.rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $affectedSjIds = DB::transaction(function () use ($order, $validated) {
            $itemIds = collect($validated['items'])->pluck('id');

            // Same row lock as DeliveryVerificationService, so a verification
            // can't slip in between this check and the update below.
            $locked = SuratJalan::whereIn('id', $order->orderItems()->whereIn('id', $itemIds)->whereNotNull('surat_jalan_id')->select('surat_jalan_id'))
                ->lockForUpdate()
                ->get()
                ->where('verification_status', SuratJalan::VERIFICATION_VERIFIED);

            if ($locked->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'STT tidak bisa diubah, dokumen sudah terverifikasi koperasi: '.$locked->pluck('sj_number')->implode(', ').'.',
                ]);
            }

            foreach ($validated['items'] as $line) {
                $order->orderItems()
                    ->whereKey($line['id'])
                    ->whereNotNull('surat_jalan_id')
                    ->update([
                        'qty_received' => $line['qty_received'],
                        'qty_rejected' => $line['qty_rejected'],
                        'rejection_reason' => $line['rejection_reason'] ?? null,
                    ]);
            }

            return $order->orderItems()
                ->whereIn('id', $itemIds)
                ->whereNotNull('surat_jalan_id')
                ->pluck('surat_jalan_id')
                ->unique();
        });

        // Filled-in Tanda Terima PDF (STT result) per delivery document.
        // The STT is already committed, so a PDF failure must not look like a
        // failed save; the next STT save renders it again.
        foreach ($affectedSjIds as $suratJalanId) {
            try {
                GenerateTandaTerimaPdfJob::dispatchSync($suratJalanId);
            } catch (Throwable $e) {
                Log::error('Gagal generate PDF Tanda Terima hasil STT.', [
                    'surat_jalan_id' => $suratJalanId,
                    'exception' => $e,
                ]);
            }
        }

        return back()->with('success', 'Data penerimaan barang (STT) berhasil disimpan.');
    }

    /**
     * Dapur adds new item lines to an Order that's already been submitted —
     * e.g. a same-day add-on or a late-evening revision. Goes through the
     * exact same Tier 1/2/3 cut-off logic as the initial submission
     * (RevisionGateService::submitRevision), not a separate code path.
     */
    public function addItems(Request $request, Order $order, RevisionGateService $revisionGate): RedirectResponse
    {
        abort_unless($order->dapur_id === $request->user()->dapur_id, 403);
        abort_if(
            in_array($order->status, ['CANCELLED', 'COMPLETED'], true),
            422,
            "Tidak bisa menambah item ke Order berstatus {$order->status}."
        );

        $validated = $request->validate(StoreOrderRequest::itemLineRules());

        $result = $revisionGate->submitRevision($order, $this->resolveOrderLines($validated['items']), $request->user());

        return back()->with(
            'success',
            count($validated['items'])." item ditambahkan (tier: {$result['tier']})."
        );
    }

    /**
     * Delivery document PDF (Surat Jalan kering / Tanda Terima basah).
     * Scoped to the user's own Dapur — a document may span several Orders,
     * so ownership is checked on the document, not on one Order.
     */
    public function downloadDocument(Request $request, SuratJalan $suratJalan, DeliveryNoteService $deliveryNotes): StreamedResponse
    {
        abort_unless($this->ownsDocument($request, $suratJalan), 403);
        $deliveryNotes->ensurePdf($suratJalan);

        return Storage::disk('local')->download(
            $suratJalan->pdf_url,
            str_replace('/', '-', $suratJalan->sj_number).'.pdf'
        );
    }

    /** Filled-in Tanda Terima (STT result) for one delivery document. */
    public function downloadDocumentTandaTerima(Request $request, SuratJalan $suratJalan): StreamedResponse
    {
        abort_unless($this->ownsDocument($request, $suratJalan), 403);
        abort_unless($suratJalan->tanda_terima_pdf_url && Storage::disk('local')->exists($suratJalan->tanda_terima_pdf_url), 404);

        return Storage::disk('local')->download(
            $suratJalan->tanda_terima_pdf_url,
            'Tanda-Terima-'.str_replace('/', '-', $suratJalan->sj_number).'.pdf'
        );
    }

    /** Arrival photos for one delivery document. */
    public function storeDocumentPhotos(Request $request, SuratJalan $suratJalan, DeliveryVerificationService $service): RedirectResponse
    {
        abort_unless($this->ownsDocument($request, $suratJalan), 403);

        $validated = $request->validate([
            'photos' => [
                'required', 'array', 'min:1', 'max:'.DeliveryVerificationService::MAX_PHOTOS_PER_DOCUMENT,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $totalKb = collect($value)->sum(fn ($file) => $file instanceof UploadedFile ? $file->getSize() / 1024 : 0);

                    if ($totalKb > DeliveryVerificationService::MAX_UPLOAD_KB) {
                        $fail('Total foto dalam sekali unggah maksimal 20 MB. Unggah sebagian dulu.');
                    }
                },
            ],
            'photos.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $service->addPhotos($suratJalan, $validated['photos'], $request->user());

        return back()->with('success', 'Foto berhasil diunggah.');
    }

    public function showDocumentPhoto(Request $request, SuratJalan $suratJalan, DeliveryDocumentPhoto $photo): StreamedResponse
    {
        abort_unless($this->ownsDocument($request, $suratJalan), 403);
        abort_unless($photo->surat_jalan_id === $suratJalan->id, 404);
        abort_unless(Storage::disk('local')->exists($photo->path), 404);

        return Storage::disk('local')->response($photo->path);
    }

    public function destroyDocumentPhoto(Request $request, SuratJalan $suratJalan, DeliveryDocumentPhoto $photo, DeliveryVerificationService $service): RedirectResponse
    {
        abort_unless($this->ownsDocument($request, $suratJalan), 403);
        abort_unless($photo->surat_jalan_id === $suratJalan->id, 404);

        $service->deletePhoto($photo);

        return back()->with('success', 'Foto dihapus.');
    }

    private function ownsDocument(Request $request, SuratJalan $suratJalan): bool
    {
        $dapurId = $suratJalan->dapur_id ?? $suratJalan->order?->dapur_id;

        return $dapurId !== null && $dapurId === $request->user()->dapur_id;
    }

    /**
     * Dapur can still fix a qty mistake on its own, but only before Ops has
     * generated a Surat Jalan for that line — once surat_jalan_id is set, the
     * quantity is what a driver is already carrying, so it's locked.
     */
    public function updateItem(Request $request, Order $order, OrderItem $orderItem): RedirectResponse
    {
        $this->authorizeEditableItem($request, $order, $orderItem);

        $validated = $request->validate([
            'qty_ordered' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
        ]);

        DB::transaction(fn () => $this->lockUnshippedItem($orderItem)->update($validated));

        return back()->with('success', 'Qty item berhasil diperbarui.');
    }

    public function destroyItem(Request $request, Order $order, OrderItem $orderItem): RedirectResponse
    {
        $this->authorizeEditableItem($request, $order, $orderItem);

        DB::transaction(fn () => $this->lockUnshippedItem($orderItem)->delete());

        return back()->with('success', 'Item berhasil dihapus dari order.');
    }

    private function authorizeEditableItem(Request $request, Order $order, OrderItem $orderItem): void
    {
        abort_unless($order->dapur_id === $request->user()->dapur_id, 403);
        abort_unless($orderItem->order_id === $order->id, 404);
        abort_unless($orderItem->surat_jalan_id === null, 422, self::ITEM_SHIPPED_MESSAGE);
    }

    /**
     * Same row lock as DeliveryNoteService's candidate query: the route-bound
     * model may predate a document generated since, which already consumed
     * stock for the old quantity.
     */
    private function lockUnshippedItem(OrderItem $orderItem): OrderItem
    {
        $locked = OrderItem::whereKey($orderItem->id)->lockForUpdate()->firstOrFail();

        abort_unless($locked->surat_jalan_id === null, 422, self::ITEM_SHIPPED_MESSAGE);

        return $locked;
    }

    /**
     * Fills supplier_id and estimated_price from the item master — Dapur
     * only submits item + qty; anything else the client sends is ignored.
     * Validation (StoreOrderRequest::itemLineRules) already guarantees each
     * item is active and has a default_supplier_id.
     *
     * @param  array<int, array{item_id: int, qty_ordered: float|string}>  $lines
     * @return array<int, array{item_id: int, supplier_id: int, qty_ordered: float, estimated_price: float}>
     */
    private function resolveOrderLines(array $lines): array
    {
        $items = Item::whereIn('id', collect($lines)->pluck('item_id'))->get()->keyBy('id');

        return collect($lines)->map(function (array $line) use ($items) {
            $item = $items[(int) $line['item_id']];

            return [
                'item_id' => $item->id,
                'supplier_id' => $item->default_supplier_id,
                'qty_ordered' => (float) $line['qty_ordered'],
                'estimated_price' => (float) ($item->base_selling_price ?? 0),
            ];
        })->values()->all();
    }

    /**
     * ORD/{YYYYMMDD}/{dapur_id}/{urutan} — a plain date+dapur_id string
     * collides as soon as one Dapur submits a second Order (any delivery
     * date) on the same calendar day, since orders.order_number is unique.
     * The per-dapur daily sequence keeps it unique regardless of how many
     * Orders are created that day.
     */
    private function generateOrderNumber(int $dapurId, int $attempt): string
    {
        $today = now();

        $sequence = Order::where('dapur_id', $dapurId)
            ->whereDate('created_at', $today->toDateString())
            ->count() + $attempt;

        return sprintf('ORD/%s/%s/%02d', $today->format('Ymd'), $dapurId, $sequence);
    }

    /**
     * One Order per (dapur_id, delivery_date) is enforced by a DB unique
     * constraint, but the "does it already exist?" read and the "create it"
     * write are two separate statements — two concurrent submissions for the
     * same Dapur+date (double-click, retried request) can both read "not
     * found" before either commits. Re-checking for the existing row and
     * retrying order_number generation on a duplicate-key error (same
     * approach as PurchaseOrderService::createPurchaseOrderWithUniqueNumber)
     * means the loser of that race ends up reusing the winner's Order
     * instead of crashing with an uncaught QueryException.
     */
    private function findOrCreateOrderSafely(int $dapurId, array $validated): Order
    {
        // Serializes Order creation per Dapur and must stay the transaction's
        // first query: the order-number count below is a plain read, so its
        // REPEATABLE READ snapshot has to be taken after this lock is held.
        Dapur::whereKey($dapurId)->lockForUpdate()->firstOrFail();

        $maxAttempts = 5;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            // lockForUpdate() reads the latest committed row: inside the caller's
            // transaction a plain read would keep returning the old snapshot
            // and never see the Order a concurrent request just created.
            $existing = Order::where('dapur_id', $dapurId)
                ->where('delivery_date', $validated['delivery_date'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            try {
                return Order::create([
                    'dapur_id' => $dapurId,
                    'delivery_date' => $validated['delivery_date'],
                    'order_number' => $this->generateOrderNumber($dapurId, $attempt),
                    'status' => 'DRAFT',
                    'notes' => $validated['notes'] ?? null,
                ]);
            } catch (QueryException $e) {
                $isDuplicateKey = (int) $e->getCode() === 23000;

                if (! $isDuplicateKey || $attempt === $maxAttempts) {
                    throw $e;
                }

                // Either another request just created the same (dapur_id,
                // delivery_date) Order, or its order_number sequence
                // collided — loop back, re-read, and try again.
            }
        }

        throw new \RuntimeException('Gagal membuat Order setelah beberapa percobaan.');
    }
}
