<?php

namespace App\Http\Resources\Pembelian;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListOrderPenawaranDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'barang_id' => $this->barang_id,

            'nama_barang' => $this->barang?->nama_barang,

            'sku_barang' => $this->barang?->sku_barang,

            'satuan' => $this->barang?->satuan?->nama_satuan,

            'qty' => $this->qty,

            'keterangan' => $this->keterangan,

        ];
    }
}