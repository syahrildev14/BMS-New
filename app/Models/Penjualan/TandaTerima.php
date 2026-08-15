<?php

namespace App\Models\Penjualan;

use Illuminate\Database\Eloquent\Model;
use App\Models\Penjualan\Penjualan;

class TandaTerima extends Model
{
    protected $fillable = [

        'penjualan_id',

        'tanggal',

        'nama_penerima',

        'jabatan',

        'catatan',

    ];

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class);
    }
}
