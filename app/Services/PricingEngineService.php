<?php

namespace App\Services;

use App\Models\DailyPrice;
use App\Models\Item;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class PricingEngineService
{
    /**
     * @param  array<int, array{item_id: int, cost_price: float}>  $pricesData
     */
    public function processDailyPrices(array $pricesData, string $date, int $userId): void
    {
        DB::transaction(function () use ($pricesData, $date, $userId) {
            foreach ($pricesData as $data) {
                $itemId = $data['item_id'];
                $costPrice = $data['cost_price'];

                $item = Item::findOrFail($itemId);
                $calculatedSellingPrice = $this->calculateMargin($costPrice, $item->margin_type, (float) $item->margin_value);

                DailyPrice::updateOrCreate(
                    ['item_id' => $itemId, 'date' => $date],
                    [
                        'cost_price' => $costPrice,
                        'calculated_price' => $calculatedSellingPrice,
                        'created_by' => $userId,
                    ]
                );

                OrderItem::whereHas('order', function ($query) use ($date) {
                    $query->where('delivery_date', $date)->where('status', 'LOCKED');
                })
                    ->where('item_id', $itemId)
                    ->update([
                        'final_cost_price' => $costPrice,
                        'final_selling_price' => $calculatedSellingPrice,
                    ]);

                $item->update([
                    'base_cost_price' => $costPrice,
                    'base_selling_price' => $calculatedSellingPrice,
                ]);
            }
        });
    }

    private function calculateMargin(float $cost, string $type, float $value): float
    {
        return $type === 'PERCENTAGE'
            ? $cost + ($cost * ($value / 100))
            : $cost + $value;
    }
}
