<?php

namespace App\Services;

use App\Jobs\GenerateInvoicePdfJob;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SplitInvoiceService
{
    /**
     * Reconcile every Surat Jalan (PRIMARY + ADDON) under one Order and split
     * the billing per supplier_id, counting only quantities that passed QC.
     */
    public function generateInvoicesForOrder(Order $order): Collection
    {
        $invoices = DB::transaction(function () use ($order) {
            $orderItems = $order->orderItems()->whereNotNull('qty_received')->get();
            $groupedItems = $orderItems->groupBy('supplier_id');
            $invoices = collect();

            foreach ($groupedItems as $supplierId => $items) {
                $totalAmount = $items->sum(function ($item) {
                    return $item->qty_received * $item->final_selling_price;
                });

                $invoiceNumber = sprintf(
                    'INV/%s/%s/SUPP-%s',
                    now()->format('Ymd'),
                    $order->dapur->code,
                    $supplierId
                );

                $invoice = Invoice::updateOrCreate(
                    [
                        'order_id' => $order->id,
                        'supplier_id' => $supplierId,
                    ],
                    [
                        'invoice_number' => $invoiceNumber,
                        'dapur_id' => $order->dapur_id,
                        'total_amount' => $totalAmount,
                        'status' => 'ISSUED',
                    ]
                );

                $invoices->push($invoice);
            }

            $order->update(['status' => 'COMPLETED']);

            return $invoices;
        });

        return $invoices->map(function (Invoice $invoice) {
            GenerateInvoicePdfJob::dispatchSync($invoice->id);

            return $invoice->refresh();
        });
    }
}
