<?php

namespace App\Http\Resources\Penjualan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PenjualanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'nomor_penjualan' => $this->nomor_penjualan,

            'tanggal' => $this->tanggal,

            'status' => $this->status,

            'keterangan' => $this->keterangan,

            'list_order' => [

                'id' => $this->listOrderPenawaran?->id,

                'nomor_order' => $this->listOrderPenawaran?->nomor_order,

                'sppg' => $this->listOrderPenawaran?->sppg?->nama_sppg,

            ],

            'gudang' => [

                'id' => $this->gudang?->id,

                'nama_gudang' => $this->gudang?->nama_gudang,

            ],

            'details' => PenjualanDetailResource::collection(
                $this->whenLoaded('details')
            ),

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,

        ];
    }
}