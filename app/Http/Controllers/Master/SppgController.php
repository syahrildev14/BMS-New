<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Sppg\StoreSppgRequest;
use App\Http\Requests\Master\Sppg\UpdateSppgRequest;
use App\Http\Resources\Master\SppgResource;
use App\Models\Master\Sppg;

class SppgController extends Controller
{
    public function index()
    {
        $perPage = request('per_page', 10);

        $sppg = Sppg::with('mitra')
            ->when(request('search'), function ($query) {
                $query->where('nama_sppg', 'like', '%' . request('search') . '%')
                    ->orWhere('kode_sppg', 'like', '%' . request('search') . '%')
                    ->orWhere('penanggung_jawab', 'like', '%' . request('search') . '%');
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Data SPPG berhasil diambil.',
            'data' => SppgResource::collection($sppg),
        ]);
    }

    public function store(StoreSppgRequest $request)
    {
        $sppg = Sppg::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data SPPG berhasil ditambahkan.',
            'data' => new SppgResource($sppg->load('mitra')),
        ], 201);
    }

    public function show(Sppg $sppg)
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail SPPG.',
            'data' => new SppgResource($sppg->load('mitra')),
        ]);
    }

    public function update(UpdateSppgRequest $request, Sppg $sppg)
    {
        $sppg->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data SPPG berhasil diperbarui.',
            'data' => new SppgResource($sppg->fresh()->load('mitra')),
        ]);
    }

    public function destroy(Sppg $sppg)
    {
        $sppg->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data SPPG berhasil dihapus.',
        ]);
    }
}