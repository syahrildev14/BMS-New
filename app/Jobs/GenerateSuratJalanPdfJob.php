<?php

namespace App\Jobs;

use App\Models\SuratJalan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Renders the physical delivery note PDF (packing list — no pricing, that's
 * Invoice's job) that the driver carries and Dapur signs off on receipt, and
 * stores its path on `surat_jalans.pdf_url` for Ops/Dapur to download.
 */
class GenerateSuratJalanPdfJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $suratJalanId) {}

    public function handle(): void
    {
        $suratJalan = SuratJalan::with(['order.dapur', 'orderItems.item.satuan', 'orderItems.supplier'])
            ->findOrFail($this->suratJalanId);

        $pdf = Pdf::loadView('pdf.surat-jalan', ['suratJalan' => $suratJalan]);

        $path = sprintf('surat-jalans/%s.pdf', str_replace('/', '-', $suratJalan->sj_number));
        Storage::disk('local')->put($path, $pdf->output());

        $suratJalan->update(['pdf_url' => $path]);
    }
}
