<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Supplier\StoreSupplierRequest;
use App\Http\Requests\Master\Supplier\UpdateSupplierRequest;
use App\Http\Resources\Master\SupplierResource;
use App\Models\Master\Supplier;

class SupplierController extends Controller
{
    public function index()
    {
        $perPage = request('per_page', 10);

        $supplier = Supplier::when(request('search'), function ($query) {
            $query->where('nama_supplier', 'like', '%' . request('search') . '%')
                ->orWhere('nomor_telepon_supplier', 'like', '%' . request('search') . '%')
                ->orWhere('kategori', 'like', '%' . request('search') . '%');
        })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Data supplier berhasil diambil.',
            'data' => SupplierResource::collection($supplier),
        ]);
    }

    public function store(StoreSupplierRequest $request)
    {
        $supplier = Supplier::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data supplier berhasil ditambahkan.',
            'data' => new SupplierResource($supplier),
        ], 201);
    }

    public function show(Supplier $supplier)
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail supplier.',
            'data' => new SupplierResource($supplier),
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        $supplier->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data supplier berhasil diperbarui.',
            'data' => new SupplierResource($supplier->fresh()),
        ]);
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data supplier berhasil dihapus.',
        ]);
    }
}
