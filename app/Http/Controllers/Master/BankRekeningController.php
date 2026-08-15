<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\BankRekening\StoreBankRekeningRequest;
use App\Http\Requests\Master\BankRekening\UpdateBankRekeningRequest;
use App\Http\Resources\Master\BankRekeningResource;
use App\Models\BankRekening;

class BankRekeningController extends Controller
{
    public function index()
    {
        $perPage = request('per_page', 10);

        $bankRekening = BankRekening::when(request('search'), function ($query) {
                $query->where('nama_bank', 'like', '%' . request('search') . '%')
                    ->orWhere('nomor_rekening', 'like', '%' . request('search') . '%')
                    ->orWhere('atas_nama', 'like', '%' . request('search') . '%');
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Data rekening bank berhasil diambil.',
            'data' => BankRekeningResource::collection($bankRekening),
        ]);
    }

    public function store(StoreBankRekeningRequest $request)
    {
        $bankRekening = BankRekening::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data rekening bank berhasil ditambahkan.',
            'data' => new BankRekeningResource($bankRekening),
        ], 201);
    }

    public function show(BankRekening $bankRekening)
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail rekening bank.',
            'data' => new BankRekeningResource($bankRekening),
        ]);
    }

    public function update(UpdateBankRekeningRequest $request, BankRekening $bankRekening)
    {
        $bankRekening->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data rekening bank berhasil diperbarui.',
            'data' => new BankRekeningResource($bankRekening->fresh()),
        ]);
    }

    public function destroy(BankRekening $bankRekening)
    {
        $bankRekening->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data rekening bank berhasil dihapus.',
        ]);
    }
}