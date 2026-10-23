<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\Gudang;
use App\Models\Item;
use App\Models\StockLot;
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

        $items = Item::with('satuan')
            ->where('category', 'DRY_GOODS')
            ->orderBy('name')
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'satuan' => $item->satuan,
                'qty_on_hand' => (float) ($stockByItem[$item->id] ?? 0),
            ]);

        return Inertia::render('gudang/stock/index', [
            'gudang' => $gudang,
            'items' => $items,
        ]);
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
