<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 18px; margin-bottom: 0; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .text-right { text-align: right; }
        .totals { margin-top: 12px; width: 100%; }
        .totals td { border: none; padding: 4px 8px; }
    </style>
</head>
<body>
    <h1>INVOICE {{ $invoice->invoice_number }}</h1>
    <p class="muted">Status: {{ $invoice->status }} &middot; Order: {{ $invoice->order->order_number }} &middot; Tanggal Kirim: {{ $invoice->order->delivery_date->format('d-m-Y') }}</p>

    <table>
        <tr>
            <td><strong>Dapur</strong><br>{{ $invoice->dapur->name }} ({{ $invoice->dapur->code }})<br>{{ $invoice->dapur->address }}</td>
            <td><strong>Supplier</strong><br>{{ $invoice->supplier->name }} ({{ $invoice->supplier->code }})<br>{{ $invoice->supplier->address }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Qty Diterima</th>
                <th>Harga Jual</th>
                <th class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->order->orderItems->where('supplier_id', $invoice->supplier_id)->whereNotNull('qty_received') as $item)
                <tr>
                    <td>{{ $item->item->name }}</td>
                    <td>{{ \App\Support\Format::qty($item->qty_received) }} {{ $item->item->satuan->name }}</td>
                    <td>{{ \App\Support\Format::rupiah($item->final_selling_price) }}</td>
                    <td class="text-right">{{ \App\Support\Format::rupiah(((float) $item->qty_received - (float) $item->qty_rejected) * (float) $item->final_selling_price) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        @foreach ($paidInvoices as $paidInvoice)
            <tr>
                <td class="text-right">Sudah Dibayar ({{ $paidInvoice->invoice_number }})</td>
                <td class="text-right" style="width: 150px;">-{{ \App\Support\Format::rupiah($paidInvoice->total_amount) }}</td>
            </tr>
        @endforeach
        <tr>
            <td class="text-right"><strong>Total Invoice</strong></td>
            <td class="text-right" style="width: 150px;">{{ \App\Support\Format::rupiah($invoice->total_amount) }}</td>
        </tr>
    </table>
</body>
</html>
