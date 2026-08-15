<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\ReturRusak\StoreReturRusakRequest;
use App\Http\Requests\Warehouse\ReturRusak\UpdateReturRusakRequest;
use App\Http\Resources\Warehouse\ReturRusakResource;
use App\Models\Warehouse\Inbound;
use App\Models\Warehouse\ReturRusak;
use App\Models\Warehouse\StokGudang;
use Illuminate\Support\Facades\DB;

class ReturRusakController extends Controller
{
    public function index()
    {
        $perPage = request('per_page', 10);

        $data = ReturRusak::with([
            'inbound.gudang',
            'details.barang',
        ])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Data retur/rusak berhasil diambil.',
            'data' => ReturRusakResource::collection($data),
        ]);
    }

    public function store(StoreReturRusakRequest $request)
    {
        $inbound = Inbound::with('details')
            ->findOrFail($request->inbound_id);

        DB::transaction(function () use ($request, $inbound) {

            $retur = ReturRusak::create([
                'inbound_id' => $inbound->id,
                'tanggal' => $request->tanggal,
                'jenis' => $request->jenis,
                'status' => 'draft',
                'keterangan' => $request->keterangan,
            ]);

            foreach ($inbound->details as $detail) {

                $retur->details()->create([
                    'barang_id' => $detail->barang_id,
                    'qty' => 0,
                    'harga' => $detail->harga,
                    'subtotal' => 0,
                    'alasan' => null,
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Retur/Rusak berhasil dibuat.',
        ], 201);
    }

    public function show(ReturRusak $returRusak)
    {
        $returRusak->load([
            'inbound.gudang',
            'details.barang.satuan',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Detail retur/rusak.',
            'data' => new ReturRusakResource($returRusak),
        ]);
    }

    public function update(
        UpdateReturRusakRequest $request,
        ReturRusak $returRusak
    ) {

        if ($returRusak->status == 'selesai') {
            return response()->json([
                'success' => false,
                'message' => 'Retur/Rusak sudah selesai.',
            ], 422);
        }

        DB::transaction(function () use ($request, $returRusak) {

            foreach ($request->details as $item) {

                $detail = $returRusak
                    ->details()
                    ->findOrFail($item['id']);

                $qty = (float) $item['qty'];

                $detail->update([
                    'qty' => $qty,
                    'subtotal' => $qty * $detail->harga,
                    'alasan' => $item['alasan'] ?? null,
                ]);

                if ($qty > 0) {

                    $stok = StokGudang::where([
                        'barang_id' => $detail->barang_id,
                        'gudang_id' => $returRusak->inbound->gudang_id,
                    ])->first();

                    if ($stok) {

                        $stok->decrement('qty', $qty);
                    }
                }
            }

            $returRusak->update([
                'status' => 'selesai',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Retur/Rusak berhasil diselesaikan.',
        ]);
    }

    public function destroy(ReturRusak $returRusak)
    {
        if ($returRusak->status == 'selesai') {
            return response()->json([
                'success' => false,
                'message' => 'Data yang sudah selesai tidak dapat dihapus.',
            ], 422);
        }

        $returRusak->delete();

        return response()->json([
            'success' => true,
            'message' => 'Retur/Rusak berhasil dihapus.',
        ]);
    }
}
