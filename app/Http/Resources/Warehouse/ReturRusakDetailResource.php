<?php

namespace App\Http\Resources\Warehouse;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturRusakDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'barang' => [
                'id' => $this->barang?->id,
                'nama_barang' => $this->barang?->nama_barang,
                'satuan' => $this->barang?->satuan?->nama_satuan,
            ],

            'qty' => $this->qty,

            'harga' => $this->harga,

            'subtotal' => $this->subtotal,

            'alasan' => $this->alasan,

        ];
    }
}