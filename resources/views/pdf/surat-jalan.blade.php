@php
    $isSuratJalan = $suratJalan->document_type === \App\Models\SuratJalan::SURAT_JALAN;
    $dapur = $suratJalan->dapur ?? $suratJalan->order?->dapur;
    $shipmentDate = $suratJalan->shipment_date ?? $suratJalan->order?->delivery_date;
    $lines = $suratJalan->orderItems->sortBy(fn ($line) => [$line->order->delivery_date, $line->id]);
@endphp
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
        .blank { min-width: 60px; }
        .signatures td { border: none; padding-top: 48px; }
        .signature-line { border-top: 1px solid #1f2937; width: 80%; margin-top: 48px; padding-top: 4px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    @if ($isSuratJalan)
        {{-- Halaman 1: Surat Jalan (bahan kering dari koperasi) --}}
        <h1>SURAT JALAN {{ $suratJalan->sj_number }}</h1>
        <p class="muted">
            <span class="badge">{{ $suratJalan->type }}</span> &middot;
            Tanggal Kirim: {{ $shipmentDate?->format('d-m-Y') }} &middot;
            Supplier: {{ $suratJalan->supplier?->name ?? $suratJalan->orderItems->pluck('supplier.name')->filter()->unique()->sort()->implode(', ') }}
        </p>

        <table>
            <tr>
                <td>
                    <strong>Dikirim ke (Dapur)</strong><br>
                    {{ $dapur?->name }} ({{ $dapur?->code }})<br>
                    {{ $dapur?->address }}
                </td>
            </tr>
        </table>

        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Supplier</th>
                    <th>Qty Dikirim</th>
                    <th>Satuan</th>
                    <th>No. Order</th>
                    <th>Untuk Tanggal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr>
                        <td>{{ $line->item->name }}</td>
                        <td>{{ $line->supplier?->name }}</td>
                        <td>{{ \App\Support\Format::qty($line->qty_ordered) }}</td>
                        <td>{{ $line->item->satuan->name }}</td>
                        <td>{{ $line->order->order_number }}</td>
                        <td>{{ $line->order->delivery_date->format('d-m-Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="signatures">
            <tr>
                <td style="width: 50%;"><div class="signature-line">Diserahkan oleh (Pengemudi Koperasi)</div></td>
                <td style="width: 50%;"><div class="signature-line">Diterima oleh (Dapur)</div></td>
            </tr>
        </table>

        <div class="page-break"></div>
    @endif

    {{-- Tanda Terima (form kosong untuk ditandatangani Dapur) --}}
    <h1>TANDA TERIMA {{ $suratJalan->sj_number }}</h1>
    <p class="muted">
        {{ $isSuratJalan ? 'Bahan Kering — dikirim Koperasi' : 'Bahan Basah — dikirim langsung Supplier' }} &middot;
        Tanggal Kirim: {{ $shipmentDate?->format('d-m-Y') }} &middot;
        Supplier: {{ $suratJalan->supplier?->name ?? $suratJalan->orderItems->pluck('supplier.name')->filter()->unique()->sort()->implode(', ') }}
    </p>
    <p class="muted">Dapur: {{ $dapur?->name }} ({{ $dapur?->code }})</p>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Supplier</th>
                <th>Qty Pesan</th>
                <th>Satuan</th>
                <th>No. Order</th>
                <th class="blank">Qty Diterima</th>
                <th class="blank">Qty Ditolak</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line->item->name }}</td>
                    <td>{{ $line->supplier?->name }}</td>
                    <td>{{ \App\Support\Format::qty($line->qty_ordered) }}</td>
                    <td>{{ $line->item->satuan->name }}</td>
                    <td>{{ $line->order->order_number }}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td style="width: 50%;">
                <div class="signature-line">Diserahkan oleh ({{ $isSuratJalan ? 'Pengemudi Koperasi' : 'Supplier' }})</div>
            </td>
            <td style="width: 50%;">
                <div class="signature-line">Diterima oleh (Dapur)</div>
            </td>
        </tr>
    </table>
</body>
</html>
