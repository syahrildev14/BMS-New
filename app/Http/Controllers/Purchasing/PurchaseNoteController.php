<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\OrderItem;
use App\Models\PurchaseNote;
use App\Models\Supplier;
use App\Models\SuratJalan;
use App\Services\PurchaseNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Nota Pembelian: dry-goods shopping receipts (→ Gudang stock) and wet-goods
 * supplier notas (one per supplier on a Tanda Terima → prices its lines).
 */
class PurchaseNoteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('purchasing/purchase-notes/index', [
            'notes' => PurchaseNote::with(['supplier:id,name', 'dapur:id,name', 'suratJalan:id,sj_number'])
                ->latest()
                ->limit(300)
                ->get(),
            'pendingTandaTerima' => $this->pendingWetNotas(),
        ]);
    }

    /**
     * (Tanda Terima, Supplier) pairs that still need a wet nota.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function pendingWetNotas(): Collection
    {
        return OrderItem::query()
            ->join('surat_jalans', 'surat_jalans.id', '=', 'order_items.surat_jalan_id')
            ->where('surat_jalans.document_type', SuratJalan::TANDA_TERIMA)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('purchase_notes')
                ->whereColumn('purchase_notes.surat_jalan_id', 'order_items.surat_jalan_id')
                ->whereColumn('purchase_notes.supplier_id', 'order_items.supplier_id'))
            ->selectRaw('order_items.surat_jalan_id, order_items.supplier_id, COUNT(*) as order_items_count')
            ->groupBy('order_items.surat_jalan_id', 'order_items.supplier_id')
            ->get()
            ->pipe(function ($rows) {
                $documents = SuratJalan::with('dapur:id,name')->findMany($rows->pluck('surat_jalan_id')->unique())->keyBy('id');
                $suppliers = Supplier::findMany($rows->pluck('supplier_id')->unique(), ['id', 'name'])->keyBy('id');

                return $rows->map(fn ($row) => [
                    'id' => (int) $row->surat_jalan_id,
                    'sj_number' => $documents[$row->surat_jalan_id]->sj_number,
                    'shipment_date' => $documents[$row->surat_jalan_id]->shipment_date?->toDateString(),
                    'dapur' => $documents[$row->surat_jalan_id]->dapur?->only(['id', 'name']),
                    'supplier' => $suppliers[$row->supplier_id]?->only(['id', 'name']),
                    'order_items_count' => (int) $row->order_items_count,
                ])->sortBy([['shipment_date', 'asc'], ['sj_number', 'asc']])->values();
            });
    }

    public function create(): Response
    {
        return $this->renderForm(null, null);
    }

    public function createWet(SuratJalan $suratJalan, Supplier $supplier): Response
    {
        abort_unless($suratJalan->document_type === SuratJalan::TANDA_TERIMA, 404);
        abort_unless($suratJalan->orderItems()->where('supplier_id', $supplier->id)->exists(), 404);
        abort_if(
            $suratJalan->purchaseNotes()->where('supplier_id', $supplier->id)->exists(),
            422,
            "Tanda Terima {$suratJalan->sj_number} sudah punya nota untuk {$supplier->name}."
        );

        return $this->renderForm(null, $suratJalan, null, $supplier);
    }

    public function store(Request $request, PurchaseNoteService $service): RedirectResponse
    {
        $note = $service->createDryNote($this->validateDry($request), $request->user());

        return redirect()->route('purchasing.purchase-notes.index')
            ->with('success', "Nota {$note->note_number} tersimpan, stok gudang bertambah.");
    }

    public function storeWet(Request $request, SuratJalan $suratJalan, Supplier $supplier, PurchaseNoteService $service): RedirectResponse
    {
        $note = $service->createWetNote($suratJalan, $supplier->id, $this->validateWet($request), $request->user());

        return redirect()->route('purchasing.purchase-notes.index')
            ->with('success', "Nota {$note->note_number} tersimpan".($note->has_discrepancy ? ' — ADA SELISIH qty dengan pesanan, perlu konfirmasi ke supplier.' : '.'));
    }

    public function edit(PurchaseNote $purchaseNote, PurchaseNoteService $service): Response
    {
        return $this->renderForm($purchaseNote, $purchaseNote->suratJalan, $service->editBlockReason($purchaseNote), $purchaseNote->supplier);
    }

    public function update(Request $request, PurchaseNote $purchaseNote, PurchaseNoteService $service): RedirectResponse
    {
        $data = $purchaseNote->category === 'DRY_GOODS' ? $this->validateDry($request) : $this->validateWet($request);
        $note = $service->updateNote($purchaseNote, $data, $request->user());

        return redirect()->route('purchasing.purchase-notes.index')
            ->with('success', "Nota {$note->note_number} berhasil dikoreksi.");
    }

    private function renderForm(?PurchaseNote $note, ?SuratJalan $tandaTerima, ?string $editBlockReason = null, ?Supplier $supplier = null): Response
    {
        $note?->load('items');

        return Inertia::render('purchasing/purchase-notes/form', [
            'note' => $note,
            'mode' => $tandaTerima ? 'WET_GOODS' : 'DRY_GOODS',
            'editBlockReason' => $editBlockReason,
            'today' => now('Asia/Jakarta')->toDateString(),
            'tandaTerima' => $tandaTerima ? [
                'id' => $tandaTerima->id,
                'sj_number' => $tandaTerima->sj_number,
                'shipment_date' => $tandaTerima->shipment_date?->toDateString(),
                'dapur_name' => $tandaTerima->dapur?->name,
                'supplier_id' => $supplier?->id,
                'supplier_name' => $supplier?->name,
                'lines' => $tandaTerima->orderItems()->where('supplier_id', $supplier?->id)->with(['item.satuan', 'order:id,order_number'])->get()
                    ->map(fn ($line) => [
                        'order_item_id' => $line->id,
                        'item_name' => $line->item->name,
                        'satuan' => $line->item->satuan?->name,
                        'order_number' => $line->order->order_number,
                        'qty_ordered' => (float) $line->qty_ordered,
                        'last_cost_price' => (float) $line->item->base_cost_price,
                    ])->values(),
            ] : null,
            'suppliers' => $tandaTerima ? [] : Supplier::orderBy('name')->get(['id', 'name']),
            'items' => $tandaTerima ? [] : Item::with('satuan')->where('category', 'DRY_GOODS')->where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'satuan_id', 'base_cost_price']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDry(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'note_date' => ['required', 'date_format:Y-m-d'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateWet(Request $request): array
    {
        return $request->validate([
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            // 0 is allowed: the supplier may not have delivered a line at all.
            'items.*.qty' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            // Required by the service when qty > 0.
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);
    }
}
