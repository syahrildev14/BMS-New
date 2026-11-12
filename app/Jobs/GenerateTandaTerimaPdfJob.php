<?php

namespace App\Jobs;

use App\Models\SuratJalan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Renders the proof-of-receipt PDF — qty shipped vs qty actually
 * received/rejected per item, as confirmed by Dapur via the STT form — and
 * stores its path on `surat_jalans.tanda_terima_pdf_url`. Regenerated every
 * time Dapur (re)submits STT for this Surat Jalan's items, same pattern as
 * GenerateInvoicePdfJob/GenerateSuratJalanPdfJob.
 */
class GenerateTandaTerimaPdfJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $suratJalanId) {}

    public function handle(): void
    {
        $suratJalan = SuratJalan::with(['dapur', 'supplier', 'order.dapur', 'orderItems.item.satuan', 'orderItems.order', 'orderItems.supplier'])
            ->findOrFail($this->suratJalanId);

        $pdf = Pdf::loadView('pdf.tanda-terima', ['suratJalan' => $suratJalan]);

        $path = sprintf('tanda-terimas/%s.pdf', str_replace('/', '-', $suratJalan->sj_number));
        Storage::disk('local')->put($path, $pdf->output());

        $suratJalan->update(['tanda_terima_pdf_url' => $path]);
    }
}
