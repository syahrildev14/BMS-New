<?php

namespace App\Models\Master;

use App\Models\Pembelian\ListOrderPenawaran;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Master\Mitra;

class Sppg extends Model
{
    protected $fillable = [
        'nama_sppg',
        'kode_sppg',
        'alamat_sppg',
        'mitra_id',
        'penanggung_jawab',
        'no_telepon',
    ];

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }

    public function listOrders()
    {
        return $this->hasMany(ListOrderPenawaran::class);
    }
}
