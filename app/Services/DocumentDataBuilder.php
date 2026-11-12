<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\OrderItem;
use App\Models\SuratJalan;
use App\Support\Format;
use Carbon\CarbonInterface;

/**
 * Turns a document into the values for its template's placeholders.
 *
 * @phpstan-type DocumentData array{anchor: string, values: array<string, string>, rows: array<int, array<string, string>>}
 */
class DocumentDataBuilder
{
    /**
     * @return DocumentData
     */
    public function forDeliveryDocument(SuratJalan $document): array
    {
        $document->loadMissing(['dapur', 'supplier', 'order.dapur', 'orderItems.item.satuan', 'orderItems.order', 'orderItems.supplier']);

        $dapur = $document->dapur ?? $document->order?->dapur;
        $lines = $document->orderItems->sortBy(fn (OrderItem $line) => [$line->order->delivery_date, $line->id])->values();

        return [
            'anchor' => 'item_no',
            'values' => [
                'nomor_surat_jalan' => $document->sj_number,
                'nomor_tanda_terima' => $document->sj_number,
                'sppg_name' => (string) $dapur?->name,
                'tanggal' => $this->date($document->shipment_date ?? $document->order?->delivery_date),
                'no_po' => $lines->pluck('order.order_number')->unique()->implode(', '),
                'supplier_name' => (string) ($document->supplier?->name ?? $lines->pluck('supplier.name')->filter()->unique()->sort()->implode(', ')),
                'armada_name' => (string) $document->armada_name,
                'no_pol' => (string) $document->plate_number,
                'driver_name' => (string) $document->driver_name,
            ],
            'rows' => $lines->map(fn (OrderItem $line, int $index) => [
                'item_no' => (string) ($index + 1),
                'item_nama_barang' => $line->item->name,
                'item_qty' => Format::qty($line->qty_ordered),
                'item_satuan' => (string) $line->item->satuan?->name,
                'item_supplier' => (string) $line->supplier?->name,
                'item_keterangan' => $line->order->order_number.' ('.$line->order->delivery_date->format('d-m-Y').')',
            ])->all(),
        ];
    }

    /**
     * @return DocumentData
     */
    public function forInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['dapur', 'order.orderItems.item.satuan']);

        $lines = $invoice->order->orderItems
            ->where('supplier_id', $invoice->supplier_id)
            ->whereNotNull('qty_received')
            ->values();

        $lineTotal = fn (OrderItem $line) => $this->billableQty($line) * (float) $line->final_selling_price;

        return [
            'anchor' => 'no',
            'values' => [
                'invoice_number' => $invoice->invoice_number,
                'grand_total' => Format::rupiah($invoice->total_amount),
                'sppg_name' => (string) $invoice->dapur?->name,
                'phone' => (string) $invoice->dapur?->pic_phone,
                'address' => (string) $invoice->dapur?->address,
                'po_number' => $invoice->order->order_number,
                'delivery_date' => $this->date($invoice->order->delivery_date),
                'invoice_date' => $this->date($invoice->issued_at ?? now()),
                'bank_name' => (string) $invoice->bank_name,
                'bank_account' => (string) $invoice->bank_account,
                'bank_owner' => (string) $invoice->bank_owner,
                'total_barang' => Format::rupiah($lines->sum($lineTotal)),
                'sudah_dibayar' => Format::rupiah($invoice->earlierPaidInvoices()->sum('total_amount')),
            ],
            'rows' => $lines->map(fn (OrderItem $line, int $index) => [
                'no' => (string) ($index + 1),
                'nama_barang' => $line->item->name,
                'qty' => Format::qty($this->billableQty($line)),
                'satuan' => (string) $line->item->satuan?->name,
                'harga_satuan' => Format::rupiah($line->final_selling_price),
                'harga_total' => Format::rupiah($lineTotal($line)),
            ])->all(),
        ];
    }

    private function billableQty(OrderItem $line): float
    {
        return (float) $line->qty_received - (float) $line->qty_rejected;
    }

    private function date(?CarbonInterface $date): string
    {
        return $date ? $date->locale('id')->translatedFormat('d F Y') : '';
    }
}
