<?php

namespace App\Jobs;

use App\Models\SuratJalan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Renders the delivery document PDF (no pricing — that's Invoice's job):
 *  - SURAT_JALAN: page 1 Surat Jalan + page 2 blank Tanda Terima form.
 *  - TANDA_TERIMA: one blank Tanda Terima form the Supplier brings along.
 * Stored on `surat_jalans.pdf_url` for Ops/Dapur to download.
 */
class GenerateSuratJalanPdfJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $suratJalanId) {}

    public function handle(): void
    {
        $suratJalan = SuratJalan::with(['dapur', 'supplier', 'order.dapur', 'orderItems.item.satuan', 'orderItems.order'])
            ->findOrFail($this->suratJalanId);

        $pdf = Pdf::loadView('pdf.surat-jalan', ['suratJalan' => $suratJalan]);

        $path = sprintf('surat-jalans/%s.pdf', str_replace('/', '-', $suratJalan->sj_number));
        Storage::disk('local')->put($path, $pdf->output());

        $suratJalan->update(['pdf_url' => $path]);
    }
}
