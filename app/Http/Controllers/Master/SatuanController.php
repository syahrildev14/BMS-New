<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Satuan\StoreSatuanRequest;
use App\Http\Requests\Master\Satuan\UpdateSatuanRequest;
use App\Http\Resources\Master\SatuanResource;
use App\Models\Master\Satuan;
use Illuminate\Http\JsonResponse;

class SatuanController extends Controller
{
    /**
     * Menampilkan daftar satuan.
     */
    public function index()
    {
        $satuan = Satuan::latest()->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Data satuan berhasil diambil.',
            'data' => SatuanResource::collection($satuan),
        ]);
    }

    /**
     * Menyimpan satuan baru.
     */
    public function store(StoreSatuanRequest $request)
    {
        $satuan = Satuan::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data satuan berhasil ditambahkan.',
            'data' => new SatuanResource($satuan),
        ], 201);
    }

    /**
     * Menampilkan detail satuan.
     */
    public function show(Satuan $satuan): SatuanResource
    {
        return new SatuanResource($satuan);
    }

    /**
     * Mengubah data satuan.
     */
    public function update(UpdateSatuanRequest $request, Satuan $satuan): SatuanResource
    {
        $satuan->update($request->validated());

        return new SatuanResource($satuan);
    }

    /**
     * Menghapus satuan.
     */
    public function destroy(Satuan $satuan): JsonResponse
    {
        $satuan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data satuan berhasil dihapus.'
        ]);
    }
}
