<?php

namespace App\Http\Resources\Warehouse;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InboundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'tanggal_masuk' => $this->tanggal_masuk,

            'status' => $this->status,

            'keterangan' => $this->keterangan,

            'gudang' => [
                'id' => $this->gudang?->id,
                'nama_gudang' => $this->gudang?->nama_gudang,
            ],

            'supplier' => [
                'id' => $this->daftarPembelanjaanSupplier?->supplier?->id,
                'nama_supplier' => $this->daftarPembelanjaanSupplier?->supplier?->nama_supplier,
            ],

            'details' => InboundDetailResource::collection(
                $this->whenLoaded('details')
            ),
        ];
    }
}