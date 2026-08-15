<?php

namespace App\Models\Penjualan;

use Illuminate\Database\Eloquent\Model;
use App\Models\Master\Barang;

class PenjualanDetail extends Model
{
    protected $fillable = [
        'penjualan_id',
        'barang_id',
        'qty',
        'harga',
        'subtotal',
    ];

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class);
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }
}