<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KaryawanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama_karyawan' => $this->nama_karyawan,
            'alamat_karyawan' => $this->alamat_karyawan,
            'nomor_telepon' => $this->nomor_telepon,
            'jabatan' => $this->jabatan,
            'tanggal_masuk' => $this->tanggal_masuk,
            'status' => $this->status,
            'created_at' => $this->created_at?->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at?->format('d-m-Y H:i:s'),
        ];
    }
}