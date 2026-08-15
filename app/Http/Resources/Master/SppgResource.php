<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SppgResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama_sppg' => $this->nama_sppg,
            'kode_sppg' => $this->kode_sppg,
            'alamat_sppg' => $this->alamat_sppg,

            'mitra' => [
                'id' => $this->mitra?->id,
                'nama_yayasan' => $this->mitra?->nama_yayasan,
            ],

            'penanggung_jawab' => $this->penanggung_jawab,
            'no_telepon' => $this->no_telepon,

            'created_at' => $this->created_at?->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at?->format('d-m-Y H:i:s'),
        ];
    }
}