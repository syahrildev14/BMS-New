<?php

namespace App\Http\Controllers\Penjualan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Penjualan\TandaTerima\StoreTandaTerimaRequest;
use App\Http\Requests\Penjualan\TandaTerima\UpdateTandaTerimaRequest;
use App\Http\Resources\Penjualan\TandaTerimaResource;
use App\Models\Penjualan\Penjualan;
use App\Models\Penjualan\TandaTerima;

class TandaTerimaController extends Controller
{
    public function index()
    {
        $perPage = request('per_page', 10);

        $data = TandaTerima::with([
            'penjualan.listOrderPenawaran.sppg',
        ])
            ->when(request('search'), function ($query) {

                $query->whereHas('penjualan.listOrderPenawaran.sppg', function ($q) {

                    $q->where(
                        'nama_sppg',
                        'like',
                        '%' . request('search') . '%'
                    );
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Data tanda terima berhasil diambil.',
            'data' => TandaTerimaResource::collection($data),
        ]);
    }

    public function store(StoreTandaTerimaRequest $request)
    {
        $penjualan = Penjualan::findOrFail($request->penjualan_id);

        if ($penjualan->status !== 'selesai') {

            return response()->json([
                'success' => false,
                'message' => 'Penjualan belum selesai.',
            ], 422);
        }

        if (
            TandaTerima::where(
                'penjualan_id',
                $penjualan->id
            )->exists()
        ) {

            return response()->json([
                'success' => false,
                'message' => 'Tanda terima sudah dibuat.',
            ], 422);
        }

        $tandaTerima = TandaTerima::create([

            'penjualan_id' => $penjualan->id,

            'tanggal' => $request->tanggal,

            'nama_penerima' => $request->nama_penerima,

            'jabatan' => $request->jabatan,

            'catatan' => $request->catatan,

        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tanda terima berhasil dibuat.',
            'data' => new TandaTerimaResource($tandaTerima),
        ], 201);
    }

    public function show(TandaTerima $tandaTerima)
    {
        $tandaTerima->load([

            'penjualan.listOrderPenawaran.sppg',

            'penjualan.gudang',

            'penjualan.details.barang.satuan',

        ]);

        return response()->json([
            'success' => true,
            'message' => 'Detail tanda terima.',
            'data' => new TandaTerimaResource($tandaTerima),
        ]);
    }

    public function update(
        UpdateTandaTerimaRequest $request,
        TandaTerima $tandaTerima
    ) {

        $tandaTerima->update([

            'tanggal' => $request->tanggal,

            'nama_penerima' => $request->nama_penerima,

            'jabatan' => $request->jabatan,

            'catatan' => $request->catatan,

        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tanda terima berhasil diperbarui.',
            'data' => new TandaTerimaResource($tandaTerima->fresh()),
        ]);
    }

    public function destroy(TandaTerima $tandaTerima)
    {
        $tandaTerima->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tanda terima berhasil dihapus.',
        ]);
    }
}