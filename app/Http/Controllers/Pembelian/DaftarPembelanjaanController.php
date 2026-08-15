<?php

namespace App\Http\Controllers\Pembelian;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pembelian\DaftarPembelanjaan\StoreDaftarPembelanjaanRequest;
use App\Http\Requests\Pembelian\DaftarPembelanjaan\UpdateDaftarPembelanjaanRequest;
use App\Http\Resources\Pembelian\DaftarPembelanjaanResource;
use App\Models\Pembelian\DaftarPembelanjaan;
use Illuminate\Support\Facades\DB;

class DaftarPembelanjaanController extends Controller
{
    public function index()
    {
        $perPage = request('per_page', 10);

        $data = DaftarPembelanjaan::with([
            'listOrderPenawaran.sppg',
            'details.barang.satuan',
            'details.supplier',
        ])
            ->when(request('search'), function ($query) {
                $query->whereHas('listOrderPenawaran', function ($q) {
                    $q->where('nomor_order', 'like', '%' . request('search') . '%');
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Data daftar pembelanjaan berhasil diambil.',
            'data' => DaftarPembelanjaanResource::collection($data),
        ]);
    }

    public function store(StoreDaftarPembelanjaanRequest $request)
    {
        if (
            DaftarPembelanjaan::where(
                'list_order_penawaran_id',
                $request->list_order_penawaran_id
            )->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'List Order ini sudah memiliki Daftar Pembelanjaan.',
            ], 422);
        }

        DB::transaction(function () use ($request) {

            $pembelanjaan = DaftarPembelanjaan::create([
                'list_order_penawaran_id' => $request->list_order_penawaran_id,
                'tanggal_pesan'           => $request->tanggal_pesan,
                'status'                  => 'draft',
                'total_harga'             => 0,
                'keterangan'              => $request->keterangan,
            ]);

            $totalHarga = 0;

            foreach ($request->details as $detail) {

                $qty = (float) $detail['qty'];
                $harga = (float) $detail['harga'];
                $subtotal = $qty * $harga;

                $totalHarga += $subtotal;

                $pembelanjaan->details()->create([
                    'barang_id'   => $detail['barang_id'],
                    'supplier_id' => $detail['supplier_id'],
                    'qty'         => $qty,
                    'harga'       => $harga,
                    'subtotal'    => $subtotal,
                ]);
            }

            $pembelanjaan->update([
                'total_harga' => $totalHarga,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar pembelanjaan berhasil ditambahkan.',
        ], 201);
    }

    public function show(DaftarPembelanjaan $daftarPembelanjaan)
    {
        $daftarPembelanjaan->load([
            'listOrderPenawaran.sppg',
            'details.barang.satuan',
            'details.supplier',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Detail daftar pembelanjaan.',
            'data' => new DaftarPembelanjaanResource($daftarPembelanjaan),
        ]);
    }

    public function update(
        UpdateDaftarPembelanjaanRequest $request,
        DaftarPembelanjaan $daftarPembelanjaan
    ) {
        DB::transaction(function () use ($request, $daftarPembelanjaan) {

            $daftarPembelanjaan->update([
                'list_order_penawaran_id' => $request->list_order_penawaran_id,
                'tanggal_pesan'           => $request->tanggal_pesan,
                'keterangan'              => $request->keterangan,
            ]);

            $daftarPembelanjaan->details()->delete();

            $totalHarga = 0;

            foreach ($request->details as $detail) {

                $qty = (float) $detail['qty'];
                $harga = (float) $detail['harga'];
                $subtotal = $qty * $harga;

                $totalHarga += $subtotal;

                $daftarPembelanjaan->details()->create([
                    'barang_id'   => $detail['barang_id'],
                    'supplier_id' => $detail['supplier_id'],
                    'qty'         => $qty,
                    'harga'       => $harga,
                    'subtotal'    => $subtotal,
                ]);
            }

            $daftarPembelanjaan->update([
                'total_harga' => $totalHarga,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar pembelanjaan berhasil diperbarui.',
        ]);
    }

    public function destroy(DaftarPembelanjaan $daftarPembelanjaan)
    {
        $daftarPembelanjaan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Daftar pembelanjaan berhasil dihapus.',
        ]);
    }
}
