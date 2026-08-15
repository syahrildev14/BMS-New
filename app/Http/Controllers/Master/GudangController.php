<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Gudang\StoreGudangRequest;
use App\Http\Requests\Master\Gudang\UpdateGudangRequest;
use App\Http\Resources\Master\GudangResource;
use App\Models\Master\Gudang;

class GudangController extends Controller
{
    public function index()
    {
        $perPage = request('per_page', 10);

        $gudang = Gudang::when(request('search'), function ($query) {
                $query->where('nama_gudang', 'like', '%' . request('search') . '%')
                    ->orWhere('nama_pic', 'like', '%' . request('search') . '%')
                    ->orWhere('no_telepon_pic', 'like', '%' . request('search') . '%');
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Data gudang berhasil diambil.',
            'data' => GudangResource::collection($gudang),
        ]);
    }

    public function store(StoreGudangRequest $request)
    {
        $gudang = Gudang::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data gudang berhasil ditambahkan.',
            'data' => new GudangResource($gudang),
        ], 201);
    }

    public function show(Gudang $gudang)
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail gudang.',
            'data' => new GudangResource($gudang),
        ]);
    }

    public function update(UpdateGudangRequest $request, Gudang $gudang)
    {
        $gudang->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data gudang berhasil diperbarui.',
            'data' => new GudangResource($gudang->fresh()),
        ]);
    }

    public function destroy(Gudang $gudang)
    {
        $gudang->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data gudang berhasil dihapus.',
        ]);
    }
}