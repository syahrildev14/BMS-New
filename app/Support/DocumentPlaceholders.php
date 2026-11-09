<?php

namespace App\Support;

use App\Models\DocumentTemplate;

/**
 * Which ${placeholders} each document type understands. The anchor is the
 * placeholder whose table row is repeated once per goods line.
 */
final class DocumentPlaceholders
{
    private const DELIVERY_ROWS = ['item_no', 'item_nama_barang', 'item_qty', 'item_satuan'];

    private const REQUIRED = [
        DocumentTemplate::SURAT_JALAN => ['nomor_surat_jalan', ...self::DELIVERY_ROWS],
        DocumentTemplate::TANDA_TERIMA => self::DELIVERY_ROWS,
        DocumentTemplate::INVOICE => ['invoice_number', 'no', 'nama_barang', 'qty', 'satuan', 'harga_satuan', 'harga_total', 'grand_total'],
    ];

    private const OPTIONAL = [
        DocumentTemplate::SURAT_JALAN => ['item_keterangan', 'sppg_name', 'tanggal', 'no_po', 'armada_name', 'no_pol', 'driver_name'],
        DocumentTemplate::TANDA_TERIMA => ['item_keterangan', 'sppg_name', 'tanggal', 'no_po', 'nomor_tanda_terima'],
        DocumentTemplate::INVOICE => ['sppg_name', 'phone', 'address', 'po_number', 'delivery_date', 'invoice_date', 'bank_name', 'bank_account', 'bank_owner', 'total_barang', 'sudah_dibayar'],
    ];

    /** @return array<int, string> */
    public static function required(string $documentType): array
    {
        return self::REQUIRED[$documentType];
    }

    /** @return array<int, string> */
    public static function allowed(string $documentType): array
    {
        return [...self::REQUIRED[$documentType], ...self::OPTIONAL[$documentType]];
    }
}
