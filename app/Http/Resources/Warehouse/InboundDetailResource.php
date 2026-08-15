<?php

namespace App\Http\Resources\Warehouse;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InboundDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'barang' => [
                'id' => $this->barang?->id,
                'sku_barang' => $this->barang?->sku_barang,
                'nama_barang' => $this->barang?->nama_barang,
                'satuan' => $this->barang?->satuan?->nama_satuan,
            ],

            'qty_pesan' => $this->qty_pesan,

            'qty_diterima' => $this->qty_diterima,

            'harga' => $this->harga,
        ];
    }
}