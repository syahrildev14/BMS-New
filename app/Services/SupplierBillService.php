<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SupplierBill;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Accounts Payable mirror of SplitInvoiceService: reconciles the same
 * order_items QC data (qty_received - qty_rejected), but at cost_price
 * instead of selling_price, to produce what BMS owes each Supplier for an
 * Order. Works uniformly for WET_GOODS (direct-from-supplier, final_cost_price
 * from the daily price) and DRY_GOODS (via Gudang, final_cost_price from the
 * FIFO-consumed stock lot) since both already land on the same column —
 * there is no separate "AP ledger" to keep in sync with order_items.
 *
 * Maker-checker, same as invoices: generateBillsForOrder() (Maker) only ever
 * produces DRAFT bills; approveBill() (Checker, supplier-bills.approve) signs
 * each one off before it's considered a real payable.
 */
class SupplierBillService
{
    public function generateBillsForOrder(Order $order): Collection
    {
        return DB::transaction(function () use ($order) {
            $orderItems = $order->orderItems()->whereNotNull('qty_received')->with('supplier')->get();
            $groupedItems = $orderItems->groupBy('supplier_id');
            $bills = collect();

            foreach ($groupedItems as $supplierId => $items) {
                $existing = SupplierBill::where('order_id', $order->id)->where('supplier_id', $supplierId)->first();

                if ($existing && $existing->status === 'ISSUED') {
                    $bills->push($existing);

                    continue;
                }

                $totalAmount = $items->sum(function ($item) {
                    return ((float) $item->qty_received - (float) $item->qty_rejected) * $item->final_cost_price;
                });

                if ($existing) {
                    $billNumber = $existing->bill_number;
                } else {
                    $dailySequence = SupplierBill::where('supplier_id', $supplierId)
                        ->whereDate('created_at', now()->toDateString())
                        ->count() + 1;

                    $billNumber = sprintf(
                        'BILL/%s/%s/%s/%02d',
                        now()->format('Ymd'),
                        $order->dapur->code,
                        $items->first()->supplier->code,
                        $dailySequence
                    );
                }

                $bill = SupplierBill::updateOrCreate(
                    [
                        'order_id' => $order->id,
                        'supplier_id' => $supplierId,
                    ],
                    [
                        'bill_number' => $billNumber,
                        'dapur_id' => $order->dapur_id,
                        'total_amount' => $totalAmount,
                        'status' => 'DRAFT',
                    ]
                );

                $bills->push($bill);
            }

            return $bills;
        });
    }

    public function approveBill(SupplierBill $bill): SupplierBill
    {
        abort_unless($bill->status === 'DRAFT', 422, 'Hanya bill berstatus DRAFT yang bisa disetujui.');

        $bill->update([
            'status' => 'ISSUED',
            'due_date' => now()->addDays((int) config('finance.supplier_bill_due_days'))->toDateString(),
        ]);

        return $bill->refresh();
    }

    public function rejectBill(SupplierBill $bill): SupplierBill
    {
        abort_unless($bill->status === 'DRAFT', 422, 'Hanya bill berstatus DRAFT yang bisa ditolak.');

        $bill->update(['status' => 'VOID']);

        return $bill->refresh();
    }

    /**
     * @param  array{payment_method: string, payment_reference?: ?string, paid_at?: ?string}  $payment
     */
    public function markAsPaid(SupplierBill $bill, array $payment): SupplierBill
    {
        abort_unless(
            in_array($bill->status, ['ISSUED', 'OVERDUE'], true),
            422,
            'Hanya bill berstatus ISSUED/OVERDUE yang bisa ditandai lunas.'
        );

        $bill->update([
            'status' => 'PAID',
            'paid_at' => $payment['paid_at'] ?? now(),
            'payment_method' => $payment['payment_method'],
            'payment_reference' => $payment['payment_reference'] ?? null,
        ]);

        return $bill->refresh();
    }
}
