<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\User;
use App\Services\PricingEngineService;
use Illuminate\Database\Seeder;

/**
 * Pre-fills today's WET_GOODS daily cost prices via the real
 * PricingEngineService (§Module 2), so admin/pricing shows a filled form
 * and items.base_cost_price/base_selling_price are already in sync when the
 * app is opened for the first time. OrderSeeder handles syncing prices for
 * its own demo Orders' (future) delivery dates separately, since that sync
 * has to happen after those Orders are LOCKED to actually take effect.
 */
class DailyPriceSeeder extends Seeder
{
    public function run(): void
    {
        $purchasing = User::where('email', 'purchasing@bms.test')->firstOrFail();

        $prices = Item::where('category', 'WET_GOODS')
            ->get()
            ->map(fn (Item $item) => ['item_id' => $item->id, 'cost_price' => (float) $item->base_cost_price])
            ->all();

        app(PricingEngineService::class)->processDailyPrices($prices, now()->toDateString(), $purchasing->id);
    }
}
