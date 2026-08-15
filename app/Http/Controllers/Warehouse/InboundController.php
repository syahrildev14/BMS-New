<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\Inbound\StoreInboundRequest;
use App\Http\Resources\Warehouse\InboundResource;
use App\Models\Pembelian\DaftarPembelanjaanSupplier;
use App\Models\Warehouse\Inbound;
use Illuminate\Support\Facades\DB;
use App\Models\Warehouse\StokGudang;
use App\Http\Requests\Warehouse\Inbound\UpdateInboundRequest;

class InboundController extends Controller
{
    public function index()
    {
        $perPage = request('per_page', 10);

        $data = Inbound::with([
            'gudang',
            'daftarPembelanjaanSupplier.supplier',
        ])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Data inbound berhasil diambil.',
            'data' => InboundResource::collection($data),
        ]);
    }

    public function store(StoreInboundRequest $request)
    {
        $supplier = DaftarPembelanjaanSupplier::with([
            'details'
        ])->findOrFail($request->daftar_pembelanjaan_supplier_id);

        DB::transaction(function () use ($request, $supplier) {

            $inbound = Inbound::create([

                'daftar_pembelanjaan_supplier_id' => $supplier->id,

                'gudang_id' => $request->gudang_id,

                'tanggal_masuk' => $request->tanggal_masuk,

                'status' => 'draft',

                'keterangan' => $request->keterangan,

            ]);

            foreach ($supplier->details as $detail) {

                $inbound->details()->create([

                    'barang_id' => $detail->barang_id,

                    'qty_pesan' => $detail->qty,

                    'qty_diterima' => $detail->qty,

                    'harga' => $detail->harga,

                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Inbound berhasil dibuat.',
        ], 201);
    }

    public function show(Inbound $inbound)
    {
        $inbound->load([

            'gudang',

            'daftarPembelanjaanSupplier.supplier',

            'details.barang.satuan',

        ]);

        return response()->json([
            'success' => true,
            'message' => 'Detail inbound.',
            'data' => new InboundResource($inbound),
        ]);
    }

    public function destroy(Inbound $inbound)
    {
        $inbound->delete();

        return response()->json([
            'success' => true,
            'message' => 'Inbound berhasil dihapus.',
        ]);
    }

    public function update(
        UpdateInboundRequest $request,
        Inbound $inbound
    ) {
        if ($inbound->status == 'selesai') {

            return response()->json([
                'success' => false,
                'message' => 'Inbound sudah selesai dan tidak dapat diubah.',
            ], 422);
        }

        DB::transaction(function () use ($request, $inbound) {

            foreach ($request->details as $item) {

                $detail = $inbound
                    ->details()
                    ->findOrFail($item['id']);

                $detail->update([
                    'qty_diterima' => $item['qty_diterima'],
                ]);

                $stok = StokGudang::firstOrNew([
                    'barang_id' => $detail->barang_id,
                    'gudang_id' => $inbound->gudang_id,
                ]);

                if (!$stok->exists) {

                    $stok->qty = $detail->qty_diterima;

                    $stok->harga_rata_rata = $detail->harga;

                    $stok->save();
                } else {

                    $qtyLama = $stok->qty;

                    $hargaLama = $stok->harga_rata_rata;

                    $qtyBaru = $detail->qty_diterima;

                    $hargaBaru = $detail->harga;

                    $totalQty = $qtyLama + $qtyBaru;

                    $hargaAverage = (
                        ($qtyLama * $hargaLama) +
                        ($qtyBaru * $hargaBaru)
                    ) / $totalQty;

                    $stok->update([
                        'qty' => $totalQty,
                        'harga_rata_rata' => $hargaAverage,
                    ]);
                }
            }

            $inbound->update([
                'status' => 'selesai',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Inbound berhasil diselesaikan.',
        ]);
    }
}
