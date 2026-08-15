<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BarangResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku_barang' => $this->sku_barang,
            'nama_barang' => $this->nama_barang,

            'satuan' => [
                'id' => $this->satuan?->id,
                'kode_satuan' => $this->satuan?->kode_satuan,
                'nama_satuan' => $this->satuan?->nama_satuan,
            ],

            'created_at' => $this->created_at?->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at?->format('d-m-Y H:i:s'),
        ];
    }
}
