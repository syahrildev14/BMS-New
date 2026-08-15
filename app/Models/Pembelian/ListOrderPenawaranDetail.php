<?php

namespace App\Models\Pembelian;

use Illuminate\Database\Eloquent\Model;
use App\Models\Master\Barang;

class ListOrderPenawaranDetail extends Model
{
    protected $fillable = [
        'list_order_penawaran_id',
        'barang_id',
        'qty',
        'keterangan',
    ];

    public function order()
    {
        return $this->belongsTo(
            ListOrderPenawaran::class,
            'list_order_penawaran_id'
        );
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }
}