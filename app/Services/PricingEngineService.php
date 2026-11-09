<?php

namespace App\Services;

use App\Models\DailyPrice;
use App\Models\Item;
use App\Models\OrderItem;
use App\Models\PurchaseNoteItem;
use Illuminate\Support\Facades\DB;

class PricingEngineService
{
    public function __construct(private readonly BillingGuard $billing) {}

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

                // Lines already priced from their own Nota Pembelian
                // (PurchaseNoteService) keep that price — the manual daily
                // price only fills lines with no delivered nota line. Billed
                // lines keep theirs too; a save covers every Dapur that day,
                // so one billed Order must not refuse the whole price.
                $lines = OrderItem::whereHas('order', function ($query) use ($date) {
                    $query->where('delivery_date', $date)->where('status', 'LOCKED');
                })
                    ->where('item_id', $itemId)
                    ->whereNotIn('id', PurchaseNoteItem::whereNotNull('order_item_id')->where('qty', '>', 0)->select('order_item_id'))
                    ->lockForUpdate()
                    ->get()
                    ->reject(fn (OrderItem $line) => $this->priceUnchanged($line, (float) $costPrice, $calculatedSellingPrice)
                        || $this->billing->hasFinalBilling(collect([$line])));

                if ($lines->isNotEmpty()) {
                    $this->billing->voidDrafts($lines);

                    OrderItem::whereKey($lines->pluck('id'))->update([
                        'final_cost_price' => $costPrice,
                        'final_selling_price' => $calculatedSellingPrice,
                    ]);
                }

                $item->update([
                    'base_cost_price' => $costPrice,
                    'base_selling_price' => $calculatedSellingPrice,
                ]);
            }
        });
    }

    private function priceUnchanged(OrderItem $line, float $costPrice, float $sellingPrice): bool
    {
        return $line->final_cost_price !== null
            && $line->final_selling_price !== null
            && round((float) $line->final_cost_price, 2) === round($costPrice, 2)
            && round((float) $line->final_selling_price, 2) === round($sellingPrice, 2);
    }

    private function calculateMargin(float $cost, string $type, float $value): float
    {
        return $type === 'PERCENTAGE'
            ? $cost + ($cost * ($value / 100))
            : $cost + $value;
    }
}
