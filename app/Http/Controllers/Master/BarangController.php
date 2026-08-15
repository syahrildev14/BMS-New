<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Barang\StoreBarangRequest;
use App\Http\Requests\Master\Barang\UpdateBarangRequest;
use App\Http\Resources\Master\BarangResource;
use App\Models\Master\Barang;

class BarangController extends Controller
{
    public function index()
    {
        $perPage = request('per_page', 10);

        $barang = Barang::with('satuan')
            ->when(request('search'), function ($query) {
                $query->where('sku_barang', 'like', '%' . request('search') . '%')
                    ->orWhere('nama_barang', 'like', '%' . request('search') . '%');
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Data barang berhasil diambil.',
            'data' => BarangResource::collection($barang),
        ]);
    }

    public function store(StoreBarangRequest $request)
    {
        $barang = Barang::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data barang berhasil ditambahkan.',
            'data' => new BarangResource($barang->load('satuan')),
        ], 201);
    }

    public function show(Barang $barang)
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail barang.',
            'data' => new BarangResource($barang->load('satuan')),
        ]);
    }

    public function update(UpdateBarangRequest $request, Barang $barang)
    {
        $barang->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data barang berhasil diperbarui.',
            'data' => new BarangResource($barang->fresh()->load('satuan')),
        ]);
    }

    public function destroy(Barang $barang)
    {
        $barang->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data barang berhasil dihapus.',
        ]);
    }
}
