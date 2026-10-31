<?php

namespace App\Services;

use App\Jobs\GenerateInvoicePdfJob;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

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
            $pendingSttCount = $order->orderItems()
                ->whereNotNull('surat_jalan_id')
                ->whereNull('qty_received')
                ->count();

            abort_if(
                $pendingSttCount > 0,
                422,
                "Tidak bisa generate invoice: {$pendingSttCount} item yang sudah dikirim (Surat Jalan) belum diinput STT (qty_received) oleh Dapur."
            );

            $orderItems = $order->orderItems()->whereNotNull('qty_received')->with(['supplier', 'item'])->get();

            // WET_GOODS only gets final_selling_price from
            // PricingEngineService syncing a daily price entered for this
            // Order's exact delivery_date — if Purchasing hasn't entered
            // that date's price yet, the column stays null. Billing
            // (qty * null) silently computes 0 instead of erroring, so this
            // must be checked explicitly rather than relying on the
            // arithmetic to fail loudly on its own.
            $unpriced = $orderItems->whereNull('final_selling_price');

            if ($unpriced->isNotEmpty()) {
                $dateStr = $order->delivery_date->toDateString();

                // Structured per-field errors (not one squished sentence) so
                // the Finance UI can render a proper alert with a bullet
                // list of item names, instead of a raw exception leaking to
                // the browser as a bare Laravel error page.
                throw ValidationException::withMessages([
                    'order_id' => "Tidak bisa generate invoice: {$unpriced->count()} item belum punya final_selling_price. Purchasing belum input Harga Harian untuk tanggal pengiriman Order ini ({$dateStr}).",
                    'unpriced_date' => $dateStr,
                    'unpriced_items' => $unpriced->pluck('item.name')->implode(', '),
                ]);
            }
            $groupedItems = $orderItems->groupBy('supplier_id');
            $invoices = collect();

            foreach ($groupedItems as $supplierId => $items) {
                $existing = Invoice::where('order_id', $order->id)->where('supplier_id', $supplierId)->first();

                // Only quantity that actually passed QC is billable (PRD
                // §Module 4 Reconciled Split Billing) — qty_rejected must be
                // subtracted, not just qty_received on its own.
                $totalAmount = $items->sum(function ($item) {
                    return ((float) $item->qty_received - (float) $item->qty_rejected) * $item->final_selling_price;
                });

                if ($existing && $existing->status === 'ISSUED') {
                    // A later add-on Surat Jalan (PRD §Module 3) for this
                    // same Order+Supplier can get STT-ed after this invoice
                    // was already approved — silently `continue`-ing here
                    // would permanently drop that amount from any invoice.
                    // Fail loudly instead so Finance has to handle the
                    // adjustment, rather than losing it unnoticed.
                    abort_if(
                        round($totalAmount, 2) !== round((float) $existing->total_amount, 2),
                        422,
                        "Invoice {$existing->invoice_number} sudah ISSUED, tapi ada perubahan kuantitas tertagih (kemungkinan Surat Jalan susulan yang baru di-STT) untuk supplier ini — total baru ".
                            number_format($totalAmount, 2).' vs invoice yang sudah terbit '.number_format((float) $existing->total_amount, 2).
                            '. Invoice yang sudah ISSUED tidak ditimpa otomatis — butuh invoice/penyesuaian susulan secara manual.'
                    );

                    $invoices->push($existing);

                    continue;
                }

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
            $invoice->update([
                'status' => 'ISSUED',
                'due_date' => now()->addDays((int) config('finance.invoice_due_days'))->toDateString(),
            ]);

            // Locked so two invoices under the same Order being approved at
            // nearly the same time can't both read "the other one isn't
            // ISSUED yet" and leave the Order stuck un-COMPLETED forever —
            // the second approval waits for the first to commit, then sees
            // its update.
            $order = Order::where('id', $invoice->order_id)->lockForUpdate()->first();

            if ($order->invoices()->where('status', '!=', 'ISSUED')->doesntExist()) {
                $order->update(['status' => 'COMPLETED']);
            }
        });

        // The invoice is already officially ISSUED at this point (committed
        // above) — a PDF render failure (template error, disk full, etc.) is
        // a secondary-artifact problem, not a reason to make this request
        // blow up with a 500 while leaving the caller unsure whether the
        // approval itself went through. Log it and let Finance regenerate
        // later; see InvoiceController::regeneratePdf().
        try {
            GenerateInvoicePdfJob::dispatchSync($invoice->id);
        } catch (Throwable $e) {
            Log::error('Gagal generate PDF invoice setelah approve.', [
                'invoice_id' => $invoice->id,
                'exception' => $e,
            ]);
        }

        return $invoice->refresh();
    }

    /**
     * Retry PDF rendering for an already-ISSUED invoice whose pdf_url never
     * got set (see approveInvoice()'s try/catch) — the only way to recover
     * without a direct DB/Tinker fix, since download() 404s without a
     * pdf_url and approve() itself only runs once per invoice (DRAFT-only).
     */
    public function regeneratePdf(Invoice $invoice): Invoice
    {
        abort_unless(
            in_array($invoice->status, ['ISSUED', 'OVERDUE', 'PAID'], true),
            422,
            'Hanya invoice yang sudah ISSUED/OVERDUE/PAID yang PDF-nya bisa di-generate ulang.'
        );

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

    /**
     * Dapur has actually paid: ISSUED/OVERDUE -> PAID. This is what makes the
     * AR side of the cash-flow/P&L reporting (FinanceAnalyticsService) real —
     * revenue is recognized at ISSUED, but cash-in is only counted at PAID.
     *
     * @param  array{payment_method: string, payment_reference?: ?string, paid_at?: ?string}  $payment
     */
    public function markAsPaid(Invoice $invoice, array $payment): Invoice
    {
        abort_unless(
            in_array($invoice->status, ['ISSUED', 'OVERDUE'], true),
            422,
            'Hanya invoice berstatus ISSUED/OVERDUE yang bisa ditandai lunas.'
        );

        $invoice->update([
            'status' => 'PAID',
            'paid_at' => $payment['paid_at'] ?? now(),
            'payment_method' => $payment['payment_method'],
            'payment_reference' => $payment['payment_reference'] ?? null,
        ]);

        return $invoice->refresh();
    }
}
