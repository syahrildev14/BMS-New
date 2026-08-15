<?php

namespace App\Http\Resources\Pembelian;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListOrderPenawaranResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'nomor_order' => $this->nomor_order,

            'tanggal_pesan' => $this->tanggal_pesan,

            'tanggal_kirim' => $this->tanggal_kirim,

            'sppg_id' => $this->sppg_id,

            'nama_sppg' => $this->sppg?->nama_sppg,

            'keterangan' => $this->keterangan,

            'details' => ListOrderPenawaranDetailResource::collection(
                $this->whenLoaded('details')
            ),

            'created_at' => $this->created_at?->format('d-m-Y H:i:s'),

            'updated_at' => $this->updated_at?->format('d-m-Y H:i:s'),

        ];
    }
}