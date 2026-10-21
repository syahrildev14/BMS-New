<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyPrice;
use App\Models\Item;
use App\Services\PricingEngineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daily price input is only relevant for WET_GOODS — DRY_GOODS stock is now
 * purchased/received via the Gudang+PurchaseOrder flow and its cost comes
 * from the FIFO-consumed stock lot at Surat Jalan time, not a manually
 * entered daily price.
 */
class PricingController extends Controller
{
    public function index(Request $request): Response
    {
        $date = $request->query('date', now()->toDateString());

        return Inertia::render('admin/pricing/index', [
            'date' => $date,
            'items' => Item::with(['defaultSupplier', 'satuan'])->where('is_active', true)->where('category', 'WET_GOODS')->get(),
            'dailyPrices' => DailyPrice::whereDate('date', $date)->get()->keyBy('item_id'),
        ]);
    }

    public function store(Request $request, PricingEngineService $service): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')->where('category', 'WET_GOODS')],
            'prices.*.cost_price' => ['required', 'numeric', 'min:0'],
        ]);

        $service->processDailyPrices($validated['prices'], $validated['date'], $request->user()->id);

        return back()->with('success', 'Harga harian berhasil diperbarui.');
    }
}
