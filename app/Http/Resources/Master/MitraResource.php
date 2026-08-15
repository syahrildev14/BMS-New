<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MitraResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama_yayasan' => $this->nama_yayasan,
            'alamat_yayasan' => $this->alamat_yayasan,
            'nama_pic_yayasan' => $this->nama_pic_yayasan,
            'no_pic_yayasan' => $this->no_pic_yayasan,
            'created_at' => $this->created_at?->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at?->format('d-m-Y H:i:s'),
        ];
    }
}