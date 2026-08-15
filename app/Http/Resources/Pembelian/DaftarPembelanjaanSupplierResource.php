<?php

namespace App\Http\Resources\Pembelian;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DaftarPembelanjaanSupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'tanggal_pembelian' => $this->tanggal_pembelian,

            'status' => $this->status,

            'total_harga' => $this->total_harga,

            'keterangan' => $this->keterangan,

            'supplier' => [
                'id' => $this->supplier?->id,
                'nama_supplier' => $this->supplier?->nama_supplier,
            ],

            'daftar_pembelanjaan' => [
                'id' => $this->daftarPembelanjaan?->id,
                'tanggal_pesan' => $this->daftarPembelanjaan?->tanggal_pesan,
                'nomor_order' => $this->daftarPembelanjaan?->listOrderPenawaran?->nomor_order,
                'sppg' => $this->daftarPembelanjaan?->listOrderPenawaran?->sppg?->nama_sppg,
            ],

            'details' => DaftarPembelanjaanSupplierDetailResource::collection(
                $this->whenLoaded('details')
            ),
        ];
    }
}