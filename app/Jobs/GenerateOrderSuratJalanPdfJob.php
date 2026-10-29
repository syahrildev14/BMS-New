<?php

namespace App\Jobs;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * One consolidated Surat Jalan PDF per Order, covering every PRIMARY/ADDON
 * SuratJalan shipment underneath it — Dapur downloads a single file instead
 * of one per shipment. Re-rendered (overwritten) each time a new SuratJalan
 * is generated for the Order, so it always reflects every shipment so far.
 */
class GenerateOrderSuratJalanPdfJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $orderId) {}

    public function handle(): void
    {
        $order = Order::with([
            'dapur',
            'suratJalans' => fn ($query) => $query->orderBy('sequence_number'),
            'suratJalans.orderItems.item.satuan',
            'suratJalans.orderItems.supplier',
        ])->findOrFail($this->orderId);

        if ($order->suratJalans->isEmpty()) {
            return;
        }

        $pdf = Pdf::loadView('pdf.order-surat-jalan', ['order' => $order]);

        $path = sprintf('surat-jalans/ORDER-%s.pdf', str_replace('/', '-', $order->order_number));
        Storage::disk('local')->put($path, $pdf->output());

        $order->update(['pdf_url' => $path]);
    }
}
