<?php

namespace App\Models\Pembelian;

use Illuminate\Database\Eloquent\Model;
use App\Models\Master\Barang;

class DaftarPembelanjaanSupplierDetail extends Model
{
    protected $fillable = [
        'daftar_pembelanjaan_supplier_id',
        'barang_id',
        'qty',
        'harga',
        'subtotal',
    ];

    public function daftarPembelanjaanSupplier()
    {
        return $this->belongsTo(
            DaftarPembelanjaanSupplier::class
        );
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }
}