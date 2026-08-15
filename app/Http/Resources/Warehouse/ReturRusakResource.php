<?php

namespace App\Http\Resources\Warehouse;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturRusakResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'tanggal' => $this->tanggal,

            'jenis' => $this->jenis,

            'status' => $this->status,

            'keterangan' => $this->keterangan,

            'inbound' => [
                'id' => $this->inbound?->id,
                'tanggal_masuk' => $this->inbound?->tanggal_masuk,
            ],

            'details' => ReturRusakDetailResource::collection(
                $this->whenLoaded('details')
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

        ];
    }
}