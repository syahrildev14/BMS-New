<?php

namespace App\Http\Controllers\Pembelian;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pembelian\ListOrderPenawaran\StoreListOrderPenawaranRequest;
use App\Http\Requests\Pembelian\ListOrderPenawaran\UpdateListOrderPenawaranRequest;
use App\Http\Resources\Pembelian\ListOrderPenawaranResource;
use App\Models\Pembelian\ListOrderPenawaran;
use Illuminate\Support\Facades\DB;

class ListOrderPenawaranController extends Controller
{
    public function index()
    {
        $perPage = request('per_page', 10);

        $orders = ListOrderPenawaran::with(['sppg'])
            ->when(request('search'), function ($query) {

                $query->where('nomor_order', 'like', '%' . request('search') . '%')
                    ->orWhereHas('sppg', function ($q) {
                        $q->where('nama_sppg', 'like', '%' . request('search') . '%');
                    });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Data List Order berhasil diambil.',
            'data' => ListOrderPenawaranResource::collection($orders),
        ]);
    }

    public function store(StoreListOrderPenawaranRequest $request)
    {
        DB::transaction(function () use ($request) {

            $order = ListOrderPenawaran::create([

                'nomor_order' => $this->generateNomorOrder(),

                'tanggal_pesan' => $request->tanggal_pesan,

                'tanggal_kirim' => $request->tanggal_kirim,

                'sppg_id' => $request->sppg_id,

                'keterangan' => $request->keterangan,

            ]);

            foreach ($request->details as $detail) {

                $order->details()->create([

                    'barang_id' => $detail['barang_id'],

                    'qty' => $detail['qty'],

                    'keterangan' => $detail['keterangan'] ?? null,

                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'List Order berhasil ditambahkan.',
        ], 201);
    }

    public function show(ListOrderPenawaran $listOrderPenawaran)
    {
        $listOrderPenawaran->load([
            'sppg',
            'details.barang.satuan'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Detail List Order.',
            'data' => new ListOrderPenawaranResource($listOrderPenawaran)
        ]);
    }

    public function update(UpdateListOrderPenawaranRequest $request, ListOrderPenawaran $listOrderPenawaran)
    {
        DB::transaction(function () use ($request, $listOrderPenawaran) {

            $listOrderPenawaran->update([

                'tanggal_pesan' => $request->tanggal_pesan,

                'tanggal_kirim' => $request->tanggal_kirim,

                'sppg_id' => $request->sppg_id,

                'keterangan' => $request->keterangan,

            ]);

            $listOrderPenawaran->details()->delete();

            foreach ($request->details as $detail) {

                $listOrderPenawaran->details()->create([

                    'barang_id' => $detail['barang_id'],

                    'qty' => $detail['qty'],

                    'keterangan' => $detail['keterangan'] ?? null,

                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'List Order berhasil diperbarui.'
        ]);
    }

    public function destroy(ListOrderPenawaran $listOrderPenawaran)
    {
        $listOrderPenawaran->delete();

        return response()->json([
            'success' => true,
            'message' => 'List Order berhasil dihapus.'
        ]);
    }

    private function generateNomorOrder()
    {
        $lastId = ListOrderPenawaran::max('id') + 1;

        return 'LOP' . str_pad($lastId, 6, '0', STR_PAD_LEFT);
    }
}
