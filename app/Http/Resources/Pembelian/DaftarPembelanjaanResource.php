<?php

namespace App\Http\Resources\Pembelian;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DaftarPembelanjaanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'list_order_penawaran_id' => $this->list_order_penawaran_id,

            'nomor_order' => $this->listOrderPenawaran?->nomor_order,

            'tanggal_pesan' => $this->tanggal_pesan,

            'total_harga' => $this->total_harga,

            'status' => $this->status,

            'keterangan' => $this->keterangan,

            'details' => DaftarPembelanjaanDetailResource::collection(
                $this->whenLoaded('details')
            ),

            'created_at' => $this->created_at?->format('d-m-Y H:i:s'),

            'updated_at' => $this->updated_at?->format('d-m-Y H:i:s'),

        ];
    }
}
