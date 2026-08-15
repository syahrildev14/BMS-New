<?php

namespace App\Models\Warehouse;

use Illuminate\Database\Eloquent\Model;
use App\Models\Master\Barang;
use App\Models\Master\Gudang;

class StokGudang extends Model
{
    protected $fillable = [
        'barang_id',
        'gudang_id',
        'qty',
        'harga_rata_rata',
    ];

    public function barang()
    {
        return $this->belongsTo(
            Barang::class
        );
    }

    public function gudang()
    {
        return $this->belongsTo(
            Gudang::class
        );
    }
}