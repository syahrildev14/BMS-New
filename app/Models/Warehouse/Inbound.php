<?php

namespace App\Models\Warehouse;

use Illuminate\Database\Eloquent\Model;
use App\Models\Master\Gudang;
use App\Models\Pembelian\DaftarPembelanjaanSupplier;
use App\Models\Warehouse\ReturRusak;

class Inbound extends Model
{
    protected $fillable = [
        'daftar_pembelanjaan_supplier_id',
        'gudang_id',
        'tanggal_masuk',
        'status',
        'keterangan',
    ];

    public function daftarPembelanjaanSupplier()
    {
        return $this->belongsTo(
            DaftarPembelanjaanSupplier::class
        );
    }

    public function gudang()
    {
        return $this->belongsTo(
            Gudang::class
        );
    }

    public function details()
    {
        return $this->hasMany(
            InboundDetail::class
        );
    }
    public function returRusaks()
    {
        return $this->hasMany(
            ReturRusak::class
        );
    }
}
