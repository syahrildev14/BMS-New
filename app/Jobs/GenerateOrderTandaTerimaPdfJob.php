<?php

namespace App\Jobs;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * One consolidated Tanda Terima PDF per Order, covering every PRIMARY/ADDON
 * SuratJalan shipment underneath it — Dapur downloads a single proof-of-
 * receipt file instead of one per shipment. Re-rendered (overwritten) each
 * time Dapur (re)submits STT, same pattern as GenerateOrderSuratJalanPdfJob.
 */
class GenerateOrderTandaTerimaPdfJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $orderId) {}

    public function handle(): void
    {
        $order = Order::with([
            'dapur',
            'suratJalans' => fn ($query) => $query->orderBy('sequence_number'),
            'suratJalans.orderItems.item.satuan',
        ])->findOrFail($this->orderId);

        if ($order->suratJalans->isEmpty()) {
            return;
        }

        $pdf = Pdf::loadView('pdf.order-tanda-terima', ['order' => $order]);

        $path = sprintf('tanda-terimas/ORDER-%s.pdf', str_replace('/', '-', $order->order_number));
        Storage::disk('local')->put($path, $pdf->output());

        $order->update(['tanda_terima_pdf_url' => $path]);
    }
}
