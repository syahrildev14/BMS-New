<?php

namespace App\Models\Warehouse;

use Illuminate\Database\Eloquent\Model;
use App\Models\Master\Barang;

class ReturRusakDetail extends Model
{
    protected $fillable = [
        'retur_rusak_id',
        'barang_id',
        'qty',
        'harga',
        'subtotal',
        'alasan',
    ];

    public function returRusak()
    {
        return $this->belongsTo(ReturRusak::class);
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }
}
