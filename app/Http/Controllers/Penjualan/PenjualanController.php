<?php

namespace App\Http\Controllers\Penjualan;

use App\Http\Controllers\Controller;
use App\Models\Penjualan\Penjualan;
use App\Models\Pembelian\ListOrderPenawaran;
use App\Models\Warehouse\StokGudang;
use App\Http\Requests\Penjualan\StorePenjualanRequest;
use App\Http\Requests\Penjualan\UpdatePenjualanRequest;
use App\Http\Resources\Penjualan\PenjualanResource;
use Illuminate\Support\Facades\DB;

class PenjualanController extends Controller
{
    public function index()
    {
        $perPage = request('per_page', 10);

        $data = Penjualan::with([
            'listOrderPenawaran.sppg',
            'gudang',
        ])
            ->when(request('search'), function ($query) {

                $query->whereHas('listOrderPenawaran', function ($q) {

                    $q->where(
                        'nomor_order',
                        'like',
                        '%' . request('search') . '%'
                    );

                });

            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Data penjualan berhasil diambil.',
            'data' => PenjualanResource::collection($data),
        ]);
    }

    public function store(StorePenjualanRequest $request)
    {
        $listOrder = ListOrderPenawaran::with('details')
            ->findOrFail($request->list_order_penawaran_id);

        DB::transaction(function () use ($request, $listOrder) {

            $penjualan = Penjualan::create([

                'nomor_penjualan' => 'PJ-' . now()->format('YmdHis'),

                'list_order_penawaran_id' => $request->list_order_penawaran_id,

                'gudang_id' => $request->gudang_id,

                'tanggal' => $request->tanggal,

                'status' => 'draft',

                'keterangan' => $request->keterangan,

            ]);

            foreach ($listOrder->details as $detail) {

                $penjualan->details()->create([

                    'barang_id' => $detail->barang_id,

                    'qty' => $detail->qty,

                    'harga' => 0,

                    'subtotal' => 0,

                ]);

            }

        });

        return response()->json([
            'success' => true,
            'message' => 'Penjualan berhasil dibuat.',
        ], 201);
    }

    public function show(Penjualan $penjualan)
    {
        $penjualan->load([
            'listOrderPenawaran.sppg',
            'gudang',
            'details.barang.satuan',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Detail penjualan.',
            'data' => new PenjualanResource($penjualan),
        ]);
    }

    public function update(
        UpdatePenjualanRequest $request,
        Penjualan $penjualan
    ) {

        if ($penjualan->status == 'selesai') {

            return response()->json([
                'success' => false,
                'message' => 'Penjualan sudah selesai.'
            ], 422);

        }

        DB::transaction(function () use ($request, $penjualan) {

            foreach ($request->details as $item) {

                $detail = $penjualan
                    ->details()
                    ->findOrFail($item['id']);

                $qty = (float) $item['qty'];
                $harga = (float) $item['harga'];

                $stok = StokGudang::where([
                    'barang_id' => $detail->barang_id,
                    'gudang_id' => $penjualan->gudang_id,
                ])->first();

                if (!$stok || $stok->qty < $qty) {

                    abort(
                        response()->json([
                            'success' => false,
                            'message' => 'Stok tidak mencukupi.'
                        ], 422)
                    );

                }

                $stok->decrement('qty', $qty);

                $detail->update([

                    'qty' => $qty,

                    'harga' => $harga,

                    'subtotal' => $qty * $harga,

                ]);

            }

            $penjualan->update([
                'status' => 'selesai',
            ]);

        });

        return response()->json([
            'success' => true,
            'message' => 'Penjualan berhasil diselesaikan.',
        ]);
    }

    public function destroy(Penjualan $penjualan)
    {
        if ($penjualan->status == 'selesai') {

            return response()->json([
                'success' => false,
                'message' => 'Penjualan yang selesai tidak dapat dihapus.'
            ], 422);

        }

        $penjualan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Penjualan berhasil dihapus.',
        ]);
    }
}