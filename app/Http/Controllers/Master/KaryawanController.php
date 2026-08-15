<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Karyawan\StoreKaryawanRequest;
use App\Http\Requests\Master\Karyawan\UpdateKaryawanRequest;
use App\Http\Resources\Master\KaryawanResource;
use App\Models\Karyawan;

class KaryawanController extends Controller
{
    public function index()
    {
        $perPage = request('per_page', 10);

        $karyawan = Karyawan::when(request('search'), function ($query) {
                $query->where('nama_karyawan', 'like', '%' . request('search') . '%')
                    ->orWhere('jabatan', 'like', '%' . request('search') . '%')
                    ->orWhere('nomor_telepon', 'like', '%' . request('search') . '%');
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Data karyawan berhasil diambil.',
            'data' => KaryawanResource::collection($karyawan),
        ]);
    }

    public function store(StoreKaryawanRequest $request)
    {
        $karyawan = Karyawan::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data karyawan berhasil ditambahkan.',
            'data' => new KaryawanResource($karyawan),
        ], 201);
    }

    public function show(Karyawan $karyawan)
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail karyawan.',
            'data' => new KaryawanResource($karyawan),
        ]);
    }

    public function update(UpdateKaryawanRequest $request, Karyawan $karyawan)
    {
        $karyawan->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data karyawan berhasil diperbarui.',
            'data' => new KaryawanResource($karyawan->fresh()),
        ]);
    }

    public function destroy(Karyawan $karyawan)
    {
        $karyawan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data karyawan berhasil dihapus.',
        ]);
    }
}