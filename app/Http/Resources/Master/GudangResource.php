<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GudangResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama_gudang' => $this->nama_gudang,
            'alamat_gudang' => $this->alamat_gudang,
            'nama_pic' => $this->nama_pic,
            'no_telepon_pic' => $this->no_telepon_pic,
            'created_at' => $this->created_at?->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at?->format('d-m-Y H:i:s'),
        ];
    }
}
