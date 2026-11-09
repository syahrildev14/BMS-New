<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\DocumentRenderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * PRD §MODULE 4 "PDF Rendering": renders the official per-supplier invoice
 * PDF (INV/{YYYYMMDD}/{DAPUR_CODE}/{SUPP_CODE}) and stores its path on
 * `invoices.pdf_url` for the Finance UI to serve back for download.
 */
class GenerateInvoicePdfJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $invoiceId) {}

    public function handle(DocumentRenderService $renderer): void
    {
        $invoice = Invoice::with(['order.orderItems.item.satuan', 'dapur', 'supplier', 'documentTemplate'])->findOrFail($this->invoiceId);

        // The lines table lists every line of this (order, supplier), so an
        // additional invoice must show what earlier paid ones already covered.
        $content = $invoice->documentTemplate
            ? $renderer->renderInvoice($invoice)
            : Pdf::loadView('pdf.invoice', ['invoice' => $invoice, 'paidInvoices' => $invoice->earlierPaidInvoices()])->output();

        $path = sprintf('invoices/%s.pdf', str_replace('/', '-', $invoice->invoice_number));
        Storage::disk('local')->put($path, $content);

        $invoice->update(['pdf_url' => $path]);
    }
}
