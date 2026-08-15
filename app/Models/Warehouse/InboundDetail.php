<?php

namespace App\Models\Warehouse;

use Illuminate\Database\Eloquent\Model;
use App\Models\Master\Barang;

class InboundDetail extends Model
{
    protected $fillable = [
        'inbound_id',
        'barang_id',
        'qty_pesan',
        'qty_diterima',
        'harga',
    ];

    public function inbound()
    {
        return $this->belongsTo(
            Inbound::class
        );
    }

    public function barang()
    {
        return $this->belongsTo(
            Barang::class
        );
    }
}