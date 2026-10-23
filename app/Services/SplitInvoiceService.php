<?php

namespace App\Services;

use App\Jobs\GenerateInvoicePdfJob;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Maker-checker on billing: generateInvoicesForOrder() (Maker, Finance) only
 * ever produces DRAFT invoices — nothing is official, no PDF, and the Order
 * stays un-COMPLETED until approveInvoice() (Checker, a separate
 * invoices.approve permission) signs each one off.
 */
class SplitInvoiceService
{
    /**
     * Reconcile every Surat Jalan (PRIMARY + ADDON) under one Order and split
     * the billing per supplier_id, counting only quantities that passed QC.
     * Invoices already ISSUED are left untouched — only DRAFT/VOID rows (or
     * brand new ones) get their totals recomputed, so a re-generate can't
     * silently undo an approved invoice.
     */
    public function generateInvoicesForOrder(Order $order): Collection
    {
        return DB::transaction(function () use ($order) {
            $orderItems = $order->orderItems()->whereNotNull('qty_received')->with('supplier')->get();
            $groupedItems = $orderItems->groupBy('supplier_id');
            $invoices = collect();

            foreach ($groupedItems as $supplierId => $items) {
                $existing = Invoice::where('order_id', $order->id)->where('supplier_id', $supplierId)->first();

                if ($existing && $existing->status === 'ISSUED') {
                    $invoices->push($existing);

                    continue;
                }

                $totalAmount = $items->sum(function ($item) {
                    return $item->qty_received * $item->final_selling_price;
                });

                // Keep a re-generated invoice's original number untouched;
                // only mint a new one (with a per-dapur/supplier/day sequence,
                // since the same dapur can bill the same supplier for a
                // different Order on the same day) when it doesn't exist yet.
                if ($existing) {
                    $invoiceNumber = $existing->invoice_number;
                } else {
                    $dailySequence = Invoice::where('dapur_id', $order->dapur_id)
                        ->where('supplier_id', $supplierId)
                        ->whereDate('created_at', now()->toDateString())
                        ->count() + 1;

                    $invoiceNumber = sprintf(
                        'INV/%s/%s/%s/%02d',
                        now()->format('Ymd'),
                        $order->dapur->code,
                        $items->first()->supplier->code,
                        $dailySequence
                    );
                }

                $invoice = Invoice::updateOrCreate(
                    [
                        'order_id' => $order->id,
                        'supplier_id' => $supplierId,
                    ],
                    [
                        'invoice_number' => $invoiceNumber,
                        'dapur_id' => $order->dapur_id,
                        'total_amount' => $totalAmount,
                        'status' => 'DRAFT',
                    ]
                );

                $invoices->push($invoice);
            }

            return $invoices;
        });
    }

    /**
     * Checker signs off a Maker's draft: becomes ISSUED and only now gets its
     * official PDF. Once every invoice under the Order is ISSUED, the Order
     * itself is COMPLETED.
     */
    public function approveInvoice(Invoice $invoice): Invoice
    {
        abort_unless($invoice->status === 'DRAFT', 422, 'Hanya invoice berstatus DRAFT yang bisa disetujui.');

        DB::transaction(function () use ($invoice) {
            $invoice->update(['status' => 'ISSUED']);

            $order = $invoice->order;

            if ($order->invoices()->where('status', '!=', 'ISSUED')->doesntExist()) {
                $order->update(['status' => 'COMPLETED']);
            }
        });

        GenerateInvoicePdfJob::dispatchSync($invoice->id);

        return $invoice->refresh();
    }

    /**
     * Checker sends a draft back: VOID, not deleted, so the billing attempt
     * stays in the audit trail. Generating again for the same Order+Supplier
     * recomputes it fresh and flips it back to DRAFT.
     */
    public function rejectInvoice(Invoice $invoice): Invoice
    {
        abort_unless($invoice->status === 'DRAFT', 422, 'Hanya invoice berstatus DRAFT yang bisa ditolak.');

        $invoice->update(['status' => 'VOID']);

        return $invoice->refresh();
    }
}
