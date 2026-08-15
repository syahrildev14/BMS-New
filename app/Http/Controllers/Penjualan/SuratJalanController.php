<?php

namespace App\Http\Controllers\Penjualan;

use App\Http\Controllers\Controller;
use App\Http\Resources\Penjualan\SuratJalanResource;
use App\Models\Penjualan\Penjualan;

class SuratJalanController extends Controller
{
    /**
     * Preview Surat Jalan
     */
    public function show(Penjualan $penjualan)
    {
        // Surat Jalan hanya boleh dibuat dari transaksi selesai
        if ($penjualan->status !== 'selesai') {
            return response()->json([
                'success' => false,
                'message' => 'Penjualan belum selesai sehingga Surat Jalan belum dapat dibuat.',
            ], 422);
        }

        $penjualan->load([
            'listOrderPenawaran.sppg',
            'gudang',
            'details.barang.satuan',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preview Surat Jalan.',
            'data' => new SuratJalanResource($penjualan),
        ]);
    }

    /**
     * Download PDF
     * Akan kita isi pada tahap berikutnya.
     */
    public function downloadPdf(Penjualan $penjualan)
    {
        if ($penjualan->status !== 'selesai') {
            return response()->json([
                'success' => false,
                'message' => 'Penjualan belum selesai sehingga Surat Jalan belum dapat dicetak.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Fitur download PDF akan dibuat pada tahap berikutnya.',
        ]);
    }
}