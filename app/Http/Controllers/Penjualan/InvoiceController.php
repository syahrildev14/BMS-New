<?php

namespace App\Http\Controllers\Penjualan;

use App\Http\Controllers\Controller;
use App\Http\Resources\Penjualan\InvoiceResource;
use App\Models\Penjualan\Penjualan;

class InvoiceController extends Controller
{
    /**
     * Preview Invoice
     */
    public function show(Penjualan $penjualan)
    {
        if ($penjualan->status !== 'selesai') {

            return response()->json([
                'success' => false,
                'message' => 'Penjualan belum selesai.',
            ], 422);
        }

        if (!$penjualan->tandaTerima) {

            return response()->json([
                'success' => false,
                'message' => 'Invoice belum dapat dibuat karena Tanda Terima belum tersedia.',
            ], 422);
        }

        $penjualan->load([
            'listOrderPenawaran.sppg',
            'gudang',
            'details.barang.satuan',
            'tandaTerima',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preview Invoice.',
            'data' => new InvoiceResource($penjualan),
        ]);
    }

    /**
     * Download PDF
     */
    public function downloadPdf(Penjualan $penjualan)
    {
        if ($penjualan->status !== 'selesai') {

            return response()->json([
                'success' => false,
                'message' => 'Penjualan belum selesai.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Generate PDF akan dibuat pada tahap berikutnya.',
        ]);
    }
}