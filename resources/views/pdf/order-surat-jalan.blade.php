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
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; background: #e5e7eb; font-size: 11px; }
        .signatures td { border: none; padding-top: 48px; }
        .signature-line { border-top: 1px solid #1f2937; width: 80%; margin-top: 48px; padding-top: 4px; }
        .shipment { margin-top: 32px; }
        .shipment:not(:first-child) { page-break-before: always; }
    </style>
</head>
<body>
    <h1>SURAT JALAN — {{ $order->order_number }}</h1>
    <p class="muted">
        Dapur: {{ $order->dapur->name }} ({{ $order->dapur->code }}) &middot;
        Tanggal Kirim: {{ $order->delivery_date->format('d-m-Y') }} &middot;
        {{ $order->suratJalans->count() }} pengiriman
    </p>

    @foreach ($order->suratJalans as $suratJalan)
        <div class="shipment">
            <h2 style="font-size: 14px; margin-bottom: 4px;">
                {{ $suratJalan->sj_number }}
                <span class="badge">{{ $suratJalan->type }}</span>
            </h2>

            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty Dikirim</th>
                        <th>Satuan</th>
                        <th>Supplier Asal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($suratJalan->orderItems as $item)
                        <tr>
                            <td>{{ $item->item->name }}</td>
                            <td>{{ \App\Support\Format::qty($item->qty_ordered) }}</td>
                            <td>{{ $item->item->satuan->name }}</td>
                            <td>{{ $item->supplier->name }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="signatures">
                <tr>
                    <td style="width: 50%;">
                        <div class="signature-line">Diserahkan oleh (Pengemudi)</div>
                    </td>
                    <td style="width: 50%;">
                        <div class="signature-line">Diterima oleh (Dapur)</div>
                    </td>
                </tr>
            </table>
        </div>
    @endforeach
</body>
</html>
