<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\Gudang;
use App\Models\Item;
use App\Models\OrderItem;
use App\Models\PurchaseNoteItem;
use App\Models\PurchaseOrderItem;
use App\Models\StockLot;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function index(): Response
    {
        $gudang = Gudang::query()->first();

        $stockByItem = $gudang
            ? StockLot::where('gudang_id', $gudang->id)
                ->selectRaw('item_id, SUM(qty_remaining) as qty_on_hand')
                ->groupBy('item_id')
                ->having('qty_on_hand', '>', 0)
                ->pluck('qty_on_hand', 'item_id')
            : collect();

        // Total barang masuk/keluar sepanjang waktu dari ledger stock_movements.
        // ORDER_OUT disimpan positif; OPNAME_ADJUST bertanda (+ surplus / - kekurangan),
        // sehingga Masuk - Keluar = Stok Tersedia.
        $movementsByItem = $gudang
            ? StockMovement::where('gudang_id', $gudang->id)
                ->selectRaw("item_id,
                    SUM(CASE WHEN type = 'PURCHASE_IN' OR (type = 'OPNAME_ADJUST' AND qty > 0) THEN qty ELSE 0 END) as qty_in,
                    SUM(CASE WHEN type = 'ORDER_OUT' THEN qty WHEN type = 'OPNAME_ADJUST' AND qty < 0 THEN -qty ELSE 0 END) as qty_out")
                ->groupBy('item_id')
                ->get()
                ->keyBy('item_id')
            : collect();

        $items = Item::with('satuan')
            ->where('category', 'DRY_GOODS')
            ->orderBy('name')
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'satuan' => $item->satuan,
                'qty_in' => (float) ($movementsByItem[$item->id]->qty_in ?? 0),
                'qty_out' => (float) ($movementsByItem[$item->id]->qty_out ?? 0),
                'qty_on_hand' => (float) ($stockByItem[$item->id] ?? 0),
            ]);

        return Inertia::render('gudang/stock/index', [
            'gudang' => $gudang,
            'items' => $items,
        ]);
    }

    /**
     * Riwayat barang masuk/keluar satu item, dengan saldo berjalan.
     * FIFO menulis satu stock_movements per lot yang tersentuh, jadi baris
     * dengan tipe + dokumen + waktu yang sama digabung menjadi satu.
     */
    public function history(Item $item): Response
    {
        abort_unless($item->category === 'DRY_GOODS', 404);

        $gudang = Gudang::query()->first();

        $movements = $gudang
            ? StockMovement::where('gudang_id', $gudang->id)
                ->where('item_id', $item->id)
                ->with([
                    'createdBy:id,name',
                    'reference' => fn (MorphTo $morph) => $morph->morphWith([
                        PurchaseOrderItem::class => ['purchaseOrder.supplier'],
                        PurchaseNoteItem::class => ['purchaseNote.supplier'],
                        OrderItem::class => ['suratJalan.dapur', 'order.dapur'],
                    ]),
                ])
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
            : collect();

        $balance = 0.0;
        $rows = $movements
            ->groupBy(fn (StockMovement $m) => implode('|', [$m->type, $m->reference_type, $m->reference_id, $m->created_at?->timestamp]))
            ->map(function ($group) use (&$balance) {
                /** @var StockMovement $first */
                $first = $group->first();
                // ORDER_OUT disimpan positif; OPNAME_ADJUST sudah bertanda.
                $signed = (float) $group->sum('qty') * ($first->type === 'ORDER_OUT' ? -1 : 1);
                $balance += $signed;

                return [
                    'id' => $first->id,
                    'date' => $first->created_at?->format('d/m/Y H:i'),
                    'type' => $first->type,
                    'qty_in' => $signed > 0 ? $signed : 0.0,
                    'qty_out' => $signed < 0 ? -$signed : 0.0,
                    'balance' => round($balance, 2),
                    ...$this->describeReference($first),
                    'created_by' => $first->createdBy?->name,
                ];
            })
            ->values()
            ->reverse()
            ->values();

        return Inertia::render('gudang/stock/history', [
            'item' => $item->load('satuan')->only(['id', 'sku', 'name', 'satuan']),
            'movements' => $rows,
        ]);
    }

    /** @return array{document: string, party: ?string} */
    private function describeReference(StockMovement $movement): array
    {
        $ref = $movement->reference;

        return match (true) {
            $ref instanceof PurchaseOrderItem => [
                'document' => 'PO '.($ref->purchaseOrder?->po_number ?? '#'.$ref->purchase_order_id),
                'party' => $ref->purchaseOrder?->supplier?->name,
            ],
            $ref instanceof PurchaseNoteItem => [
                'document' => 'Nota Belanja '.($ref->purchaseNote?->note_number ?? '#'.$ref->purchase_note_id),
                'party' => $ref->purchaseNote?->supplier?->name,
            ],
            $ref instanceof OrderItem => [
                'document' => $ref->suratJalan ? 'Surat Jalan '.$ref->suratJalan->sj_number : 'Order #'.$ref->order_id,
                'party' => $ref->suratJalan?->dapur?->name ?? $ref->order?->dapur?->name,
            ],
            $movement->type === 'OPNAME_ADJUST' => ['document' => 'Stock Opname', 'party' => null],
            default => ['document' => '-', 'party' => null],
        };
    }

    public function opname(Request $request, Item $item, StockService $service): RedirectResponse
    {
        abort_unless($item->category === 'DRY_GOODS', 422, 'Stock Opname hanya berlaku untuk barang DRY_GOODS.');

        $validated = $request->validate([
            'physical_qty' => ['required', 'numeric', 'min:0'],
        ]);

        $gudang = Gudang::query()->firstOrFail();
        $result = $service->recordOpname($gudang, $item, (float) $validated['physical_qty'], $request->user());

        $message = $result['delta'] == 0.0
            ? "Stok {$item->name} sudah sesuai, tidak ada penyesuaian."
            : sprintf(
                'Stok %s disesuaikan dari %s menjadi %s (%s%s).',
                $item->name,
                number_format($result['system_qty'], 2),
                number_format($result['physical_qty'], 2),
                $result['delta'] > 0 ? '+' : '',
                number_format($result['delta'], 2)
            );

        return back()->with('success', $message);
    }
}
