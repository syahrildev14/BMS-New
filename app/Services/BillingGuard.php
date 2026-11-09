<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SupplierBill;
use App\Models\SuratJalan;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The one rule for "may these order lines' quantities or prices still
 * change?": not once a final invoice or supplier bill covers them. DRAFT ones
 * are voided when the lines do change, so they can't be approved with an old
 * amount; regenerating recomputes them.
 */
class BillingGuard
{
    public const FINAL_STATUSES = ['ISSUED', 'OVERDUE', 'PAID'];

    private const PRICE_COLUMNS = [
        Invoice::class => 'final_selling_price',
        SupplierBill::class => 'final_cost_price',
    ];

    /**
     * True when a final invoice or bill covers any of these lines. Lines have
     * no link to the document billing them, so coverage is read from the
     * amount: if the final total already equals what the pair's other lines
     * add up to, these lines (e.g. a late shipment) were not billed on it.
     *
     * @param  Collection<int, OrderItem>  $lines  must include the id column
     */
    public function hasFinalBilling(Collection $lines): bool
    {
        $lineIds = $lines->pluck('id');

        foreach ($this->pairs($lines) as $line) {
            foreach (self::PRICE_COLUMNS as $model => $priceColumn) {
                // Locking read: a plain one could miss an approval that
                // committed after this transaction's snapshot was taken.
                $final = $model::where('order_id', $line->order_id)
                    ->where('supplier_id', $line->supplier_id)
                    ->whereIn('status', self::FINAL_STATUSES)
                    ->lockForUpdate()
                    ->get();

                if ($final->isEmpty()) {
                    continue;
                }

                $otherLines = OrderItem::where('order_id', $line->order_id)
                    ->where('supplier_id', $line->supplier_id)
                    ->whereNotIn('id', $lineIds)
                    ->whereNotNull('qty_received')
                    ->get();

                if (round((float) $final->sum('total_amount'), 2) !== $this->billableTotal($otherLines, $priceColumn)) {
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
            foreach (array_keys(self::PRICE_COLUMNS) as $model) {
                $model::where('order_id', $line->order_id)
                    ->where('supplier_id', $line->supplier_id)
                    ->where('status', 'DRAFT')
                    ->update(['status' => 'VOID']);
            }
        }
    }

    /**
     * Only quantity that passed QC is billable (qty_received - qty_rejected).
     *
     * @param  Collection<int, OrderItem>  $lines
     */
    public function billableTotal(Collection $lines, string $priceColumn): float
    {
        return round((float) $lines->sum(fn (OrderItem $line) => $this->billableQty($line) * (float) $line->{$priceColumn}), 2);
    }

    /**
     * Lines that would be billed but have no price. Without this check
     * qty * null silently computes 0. A line with nothing to bill needs no
     * price: an undelivered wet line never gets one from its nota.
     *
     * @param  Collection<int, OrderItem>  $lines
     * @return Collection<int, OrderItem>
     */
    public function unpricedLines(Collection $lines, string $priceColumn): Collection
    {
        return $lines->filter(fn (OrderItem $line) => $line->{$priceColumn} === null && $this->billableQty($line) > 0);
    }

    /**
     * Crosscheck H+1: every per-supplier document under this Order must be
     * VERIFIED. Takes the document row lock DeliveryVerificationService uses,
     * so billing and a reopen can't interleave. Legacy documents (no
     * supplier_id) predate the crosscheck and are exempt.
     */
    public function assertDocumentsVerified(Order $order): void
    {
        $unverified = SuratJalan::whereIn('id', $order->orderItems()->whereNotNull('surat_jalan_id')->select('surat_jalan_id'))
            ->whereNotNull('supplier_id')
            ->lockForUpdate()
            ->get()
            ->where('verification_status', '!=', SuratJalan::VERIFICATION_VERIFIED)
            ->pluck('sj_number');

        if ($unverified->isNotEmpty()) {
            throw ValidationException::withMessages([
                'order_id' => 'Dokumen belum lolos Crosscheck H+1: '.$unverified->implode(', ').'.',
            ]);
        }
    }

    /**
     * Refuses a draft whose amount no longer matches its lines, e.g. an add-on
     * document verified after the draft was generated. Rows are locked so a
     * concurrent price change is either seen here or waits for this approval.
     * An additional draft only covers what PAID ones for the pair do not.
     */
    public function assertDraftAmountCurrent(Order $order, Invoice|SupplierBill $draft): void
    {
        $lines = $order->orderItems()
            ->where('supplier_id', $draft->supplier_id)
            ->whereNotNull('qty_received')
            ->lockForUpdate()
            ->get();

        $paid = (float) $draft::where('order_id', $order->id)
            ->where('supplier_id', $draft->supplier_id)
            ->where('status', 'PAID')
            ->sum('total_amount');

        if (round((float) $draft->total_amount, 2) !== round($this->billableTotal($lines, self::PRICE_COLUMNS[$draft::class]) - $paid, 2)) {
            throw ValidationException::withMessages([
                'order_id' => 'Total draft ini sudah tidak sesuai dengan barang terbaru. Generate ulang lalu setujui kembali.',
            ]);
        }
    }

    private function billableQty(OrderItem $line): float
    {
        return (float) $line->qty_received - (float) $line->qty_rejected;
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
