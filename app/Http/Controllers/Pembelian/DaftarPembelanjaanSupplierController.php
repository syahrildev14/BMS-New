<?php

namespace App\Http\Controllers\Pembelian;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pembelian\DaftarPembelanjaanSupplier\StoreDaftarPembelanjaanSupplierRequest;
use App\Http\Resources\Pembelian\DaftarPembelanjaanSupplierResource;
use App\Models\Pembelian\DaftarPembelanjaan;
use App\Models\Pembelian\DaftarPembelanjaanSupplier;
use Illuminate\Support\Facades\DB;

class DaftarPembelanjaanSupplierController extends Controller
{
    public function index()
    {
        $perPage = request('per_page', 10);

        $data = DaftarPembelanjaanSupplier::with([
            'supplier',
            'daftarPembelanjaan.listOrderPenawaran.sppg',
        ])
            ->when(request('search'), function ($query) {
                $query->whereHas('supplier', function ($q) {
                    $q->where('nama_supplier', 'like', '%' . request('search') . '%');
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Data daftar pembelanjaan supplier berhasil diambil.',
            'data' => DaftarPembelanjaanSupplierResource::collection($data),
        ]);
    }

    public function store(StoreDaftarPembelanjaanSupplierRequest $request)
    {
        $pembelanjaan = DaftarPembelanjaan::with([
            'details.barang',
            'details.supplier',
        ])->findOrFail($request->daftar_pembelanjaan_id);

        DB::transaction(function () use ($request, $pembelanjaan) {

            $grouped = $pembelanjaan->details->groupBy('supplier_id');

            foreach ($grouped as $supplierId => $details) {

                $header = DaftarPembelanjaanSupplier::create([
                    'daftar_pembelanjaan_id' => $pembelanjaan->id,
                    'supplier_id'            => $supplierId,
                    'tanggal_pembelian'      => $request->tanggal_pembelian,
                    'status'                 => 'draft',
                    'total_harga'            => 0,
                    'keterangan'             => $request->keterangan,
                ]);

                $totalHarga = 0;

                foreach ($details as $detail) {

                    $subtotal = $detail->qty * $detail->harga;

                    $totalHarga += $subtotal;

                    $header->details()->create([
                        'barang_id' => $detail->barang_id,
                        'qty'       => $detail->qty,
                        'harga'     => $detail->harga,
                        'subtotal'  => $subtotal,
                    ]);
                }

                $header->update([
                    'total_harga' => $totalHarga,
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar pembelanjaan supplier berhasil dibuat.',
        ], 201);
    }

    public function show(DaftarPembelanjaanSupplier $daftarPembelanjaanSupplier)
    {
        $daftarPembelanjaanSupplier->load([
            'supplier',
            'daftarPembelanjaan.listOrderPenawaran.sppg',
            'details.barang.satuan',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Detail daftar pembelanjaan supplier.',
            'data' => new DaftarPembelanjaanSupplierResource(
                $daftarPembelanjaanSupplier
            ),
        ]);
    }

    public function destroy(DaftarPembelanjaanSupplier $daftarPembelanjaanSupplier)
    {
        $daftarPembelanjaanSupplier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Daftar pembelanjaan supplier berhasil dihapus.',
        ]);
    }
}
