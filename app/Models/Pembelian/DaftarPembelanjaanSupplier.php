<?php

namespace App\Models\Pembelian;

use Illuminate\Database\Eloquent\Model;
use App\Models\Master\Supplier;
use App\Models\Warehouse\Inbound;

class DaftarPembelanjaanSupplier extends Model
{
    protected $fillable = [
        'daftar_pembelanjaan_id',
        'supplier_id',
        'tanggal_pembelian',
        'total_harga',
        'status',
        'keterangan',
    ];

    public function daftarPembelanjaan()
    {
        return $this->belongsTo(DaftarPembelanjaan::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function details()
    {
        return $this->hasMany(
            DaftarPembelanjaanSupplierDetail::class
        );
    }
    public function inbounds()
    {
        return $this->hasMany(
            Inbound::class
        );
    }
}
