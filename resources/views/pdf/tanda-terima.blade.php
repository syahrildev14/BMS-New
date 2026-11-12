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
        .status-ok { color: #047857; font-weight: bold; }
        .status-diff { color: #b45309; font-weight: bold; }
        .signatures td { border: none; padding-top: 48px; }
        .signature-line { border-top: 1px solid #1f2937; width: 80%; margin-top: 48px; padding-top: 4px; }
    </style>
</head>
<body>
    <h1>TANDA TERIMA — {{ $suratJalan->sj_number }}</h1>
    <p class="muted">
        @php
            $dapur = $suratJalan->dapur ?? $suratJalan->order?->dapur;
            $shipmentDate = $suratJalan->shipment_date ?? $suratJalan->order?->delivery_date;
        @endphp
        Order: {{ $suratJalan->orderItems->pluck('order.order_number')->unique()->implode(', ') }} &middot;
        Dapur: {{ $dapur?->name }} ({{ $dapur?->code }}) &middot;
        Supplier: {{ $suratJalan->supplier?->name ?? $suratJalan->orderItems->pluck('supplier.name')->filter()->unique()->sort()->implode(', ') }} &middot;
        Tanggal Kirim: {{ $shipmentDate?->format('d-m-Y') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Supplier</th>
                <th>Qty Dikirim</th>
                <th>Qty Diterima</th>
                <th>Qty Ditolak</th>
                <th>Alasan Tolak</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($suratJalan->orderItems as $item)
                <tr>
                    <td>{{ $item->item->name }}</td>
                    <td>{{ $item->supplier?->name }}</td>
                    <td>{{ \App\Support\Format::qty($item->qty_ordered) }} {{ $item->item->satuan->name }}</td>
                    <td>{{ \App\Support\Format::qty($item->qty_received) }}</td>
                    <td>{{ \App\Support\Format::qty($item->qty_rejected) }}</td>
                    <td>{{ $item->rejection_reason ?? '-' }}</td>
                    <td>
                        @if (is_null($item->qty_received))
                            <span class="muted">Belum dikonfirmasi</span>
                        @elseif ((float) $item->qty_rejected > 0)
                            <span class="status-diff">Ada Selisih</span>
                        @else
                            <span class="status-ok">Diterima Lengkap</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td style="width: 50%;">
                <div class="signature-line">Diserahkan oleh ({{ $suratJalan->document_type === \App\Models\SuratJalan::TANDA_TERIMA ? 'Supplier' : 'Pengemudi' }})</div>
            </td>
            <td style="width: 50%;">
                <div class="signature-line">Diterima &amp; Dikonfirmasi oleh (Dapur)</div>
            </td>
        </tr>
    </table>
</body>
</html>
