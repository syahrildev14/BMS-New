<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Armada\StoreArmadaRequest;
use App\Http\Requests\Master\Armada\UpdateArmadaRequest;
use App\Http\Resources\Master\ArmadaResource;
use App\Models\Armada;

class ArmadaController extends Controller
{
    public function index()
    {
        $perPage = request('per_page', 10);

        $armada = Armada::when(request('search'), function ($query) {
            $query->where('nama_unit', 'like', '%' . request('search') . '%')
                ->orWhere('nomor_polisi', 'like', '%' . request('search') . '%')
                ->orWhere('jenis_kendaraan', 'like', '%' . request('search') . '%');
        })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Data armada berhasil diambil.',
            'data' => ArmadaResource::collection($armada),
        ]);
    }

    public function store(StoreArmadaRequest $request)
    {
        $armada = Armada::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data armada berhasil ditambahkan.',
            'data' => new ArmadaResource($armada),
        ], 201);
    }

    public function show(Armada $armada)
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail armada.',
            'data' => new ArmadaResource($armada),
        ]);
    }

    public function update(UpdateArmadaRequest $request, Armada $armada)
    {
        $armada->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data armada berhasil diperbarui.',
            'data' => new ArmadaResource($armada->fresh()),
        ]);
    }

    public function destroy(Armada $armada)
    {
        $armada->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data armada berhasil dihapus.',
        ]);
    }
}
