<?php

namespace App\Jobs;

use App\Models\Invoice;
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

    public function handle(): void
    {
        $invoice = Invoice::with(['order.orderItems.item.satuan', 'dapur', 'supplier'])->findOrFail($this->invoiceId);

        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice]);

        $path = sprintf('invoices/%s.pdf', str_replace('/', '-', $invoice->invoice_number));
        Storage::disk('local')->put($path, $pdf->output());

        $invoice->update(['pdf_url' => $path]);
    }
}
