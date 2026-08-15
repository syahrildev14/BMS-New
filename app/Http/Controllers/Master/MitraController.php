<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Mitra\StoreMitraRequest;
use App\Http\Requests\Master\Mitra\UpdateMitraRequest;
use App\Http\Resources\Master\MitraResource;
use App\Models\Master\Mitra;

class MitraController extends Controller
{
    public function index()
    {
        $perPage = request('per_page', 10);

        $mitra = Mitra::when(request('search'), function ($query) {
            $query->where('nama_yayasan', 'like', '%' . request('search') . '%')
                ->orWhere('nama_pic_yayasan', 'like', '%' . request('search') . '%')
                ->orWhere('no_pic_yayasan', 'like', '%' . request('search') . '%');
        })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Data mitra berhasil diambil.',
            'data' => MitraResource::collection($mitra),
        ]);
    }

    public function store(StoreMitraRequest $request)
    {
        $mitra = Mitra::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data mitra berhasil ditambahkan.',
            'data' => new MitraResource($mitra),
        ], 201);
    }

    public function show(Mitra $mitra)
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail mitra.',
            'data' => new MitraResource($mitra),
        ]);
    }

    public function update(UpdateMitraRequest $request, Mitra $mitra)
    {
        $mitra->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data mitra berhasil diperbarui.',
            'data' => new MitraResource($mitra->fresh()),
        ]);
    }

    public function destroy(Mitra $mitra)
    {
        $mitra->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data mitra berhasil dihapus.',
        ]);
    }
}
