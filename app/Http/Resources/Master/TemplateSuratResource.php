<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateSuratResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id'=>$this->id,

            'nama_template'=>$this->nama_template,

            'jenis_template'=>$this->jenis_template,

            'isi_template'=>$this->isi_template,

            'status'=>$this->status,

            'created_at'=>$this->created_at?->format('d-m-Y H:i:s'),

            'updated_at'=>$this->updated_at?->format('d-m-Y H:i:s'),

        ];
    }
}