<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\Gudang;
use App\Models\Item;
use App\Models\StockLot;
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
}
