<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\OrderItem;
use App\Models\SupplierBill;
use Illuminate\Support\Collection;

/**
 * The one rule for "may these order lines' quantities or prices still
 * change?": not once an invoice or supplier bill for their (order, supplier)
 * is final. DRAFT ones are voided when the lines do change, so they can't be
 * approved with an old amount; regenerating recomputes them.
 */
class BillingGuard
{
    public const FINAL_STATUSES = ['ISSUED', 'OVERDUE', 'PAID'];

    /**
     * @param  Collection<int, OrderItem>  $lines
     */
    public function hasFinalBilling(Collection $lines): bool
    {
        foreach ($this->pairs($lines) as $line) {
            foreach ([Invoice::class, SupplierBill::class] as $model) {
                $isFinal = $model::where('order_id', $line->order_id)
                    ->where('supplier_id', $line->supplier_id)
                    ->whereIn('status', self::FINAL_STATUSES)
                    ->exists();

                if ($isFinal) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, OrderItem>  $lines
     */
    public function voidDrafts(Collection $lines): void
    {
        foreach ($this->pairs($lines) as $line) {
            foreach ([Invoice::class, SupplierBill::class] as $model) {
                $model::where('order_id', $line->order_id)
                    ->where('supplier_id', $line->supplier_id)
                    ->where('status', 'DRAFT')
                    ->update(['status' => 'VOID']);
            }
        }
    }

    /**
     * @param  Collection<int, OrderItem>  $lines
     * @return Collection<int, OrderItem>
     */
    private function pairs(Collection $lines): Collection
    {
        return $lines->unique(fn (OrderItem $line) => "{$line->order_id}-{$line->supplier_id}");
    }
}
