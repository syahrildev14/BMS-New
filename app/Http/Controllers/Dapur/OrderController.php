<?php

namespace App\Http\Controllers\Dapur;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Jobs\GenerateOrderTandaTerimaPdfJob;
use App\Jobs\GenerateTandaTerimaPdfJob;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Services\RevisionGateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $dapurId = $request->user()->dapur_id;

        return Inertia::render('dapur/orders/index', [
            'orders' => Order::where('dapur_id', $dapurId)
                ->latest('delivery_date')
                ->with('orderItems.item')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('dapur/orders/create', [
            'items' => Item::with(['defaultSupplier', 'satuan'])->where('is_active', true)->get(),
            'suppliers' => Supplier::all(),
        ]);
    }

    public function store(StoreOrderRequest $request, RevisionGateService $revisionGate): RedirectResponse
    {
        $dapurId = $request->user()->dapur_id;
        $validated = $request->validated();

        $order = Order::firstOrCreate(
            ['dapur_id' => $dapurId, 'delivery_date' => $validated['delivery_date']],
            [
                'order_number' => $this->generateOrderNumber($dapurId),
                'status' => 'DRAFT',
                'notes' => $validated['notes'] ?? null,
            ]
        );

        $result = $revisionGate->submitRevision($order, $validated['items'], $request->user());

        return redirect()
            ->route('dapur.orders.index')
            ->with('success', "Order berhasil dikirim (tier: {$result['tier']}).");
    }

    public function show(Request $request, Order $order): Response
    {
        abort_unless($order->dapur_id === $request->user()->dapur_id, 403);

        return Inertia::render('dapur/orders/show', [
            'order' => $order->load('orderItems.item', ['suratJalans' => fn ($query) => $query->orderBy('sequence_number')]),
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
            'items.*.qty_received' => ['required', 'numeric', 'min:0'],
            'items.*.qty_rejected' => ['required', 'numeric', 'min:0'],
            'items.*.rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $affectedSjIds = DB::transaction(function () use ($order, $validated) {
            $itemIds = collect($validated['items'])->pluck('id');

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

        // Per-SJ Tanda Terima PDF (operational record, same granularity as the
        // Surat Jalan each shipment travelled with) ...
        foreach ($affectedSjIds as $suratJalanId) {
            GenerateTandaTerimaPdfJob::dispatchSync($suratJalanId);
        }

        // ... plus one combined Tanda Terima PDF for the whole Order, which is
        // what Dapur actually downloads — covers every shipment confirmed so
        // far, not just the ones touched by this particular STT submission.
        if ($affectedSjIds->isNotEmpty()) {
            GenerateOrderTandaTerimaPdfJob::dispatchSync($order->id);
        }

        return back()->with('success', 'Data penerimaan barang (STT) berhasil disimpan.');
    }

    public function downloadSuratJalan(Request $request, Order $order): StreamedResponse
    {
        abort_unless($order->dapur_id === $request->user()->dapur_id, 403);
        abort_unless($order->pdf_url && Storage::disk('local')->exists($order->pdf_url), 404);

        return Storage::disk('local')->download(
            $order->pdf_url,
            'Surat-Jalan-' . str_replace('/', '-', $order->order_number) . '.pdf'
        );
    }

    public function downloadTandaTerima(Request $request, Order $order): StreamedResponse
    {
        abort_unless($order->dapur_id === $request->user()->dapur_id, 403);
        abort_unless($order->tanda_terima_pdf_url && Storage::disk('local')->exists($order->tanda_terima_pdf_url), 404);

        return Storage::disk('local')->download(
            $order->tanda_terima_pdf_url,
            'Tanda-Terima-' . str_replace('/', '-', $order->order_number) . '.pdf'
        );
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
            'qty_ordered' => ['required', 'numeric', 'min:0.01'],
        ]);

        $orderItem->update($validated);

        return back()->with('success', 'Qty item berhasil diperbarui.');
    }

    public function destroyItem(Request $request, Order $order, OrderItem $orderItem): RedirectResponse
    {
        $this->authorizeEditableItem($request, $order, $orderItem);

        $orderItem->delete();

        return back()->with('success', 'Item berhasil dihapus dari order.');
    }

    private function authorizeEditableItem(Request $request, Order $order, OrderItem $orderItem): void
    {
        abort_unless($order->dapur_id === $request->user()->dapur_id, 403);
        abort_unless($orderItem->order_id === $order->id, 404);
        abort_unless($orderItem->surat_jalan_id === null, 422, 'Item ini sudah punya Surat Jalan dan tidak bisa diedit lagi.');
    }

    /**
     * ORD/{YYYYMMDD}/{dapur_id}/{urutan} — a plain date+dapur_id string
     * collides as soon as one Dapur submits a second Order (any delivery
     * date) on the same calendar day, since orders.order_number is unique.
     * The per-dapur daily sequence keeps it unique regardless of how many
     * Orders are created that day.
     */
    private function generateOrderNumber(int $dapurId): string
    {
        $today = now();

        $sequence = Order::where('dapur_id', $dapurId)
            ->whereDate('created_at', $today->toDateString())
            ->count() + 1;

        return sprintf('ORD/%s/%s/%02d', $today->format('Ymd'), $dapurId, $sequence);
    }
}
