<?php

namespace App\Models\Pembelian;

use Illuminate\Database\Eloquent\Model;
use App\Models\Master\Barang;
use App\Models\Master\Supplier;

class DaftarPembelanjaanDetail extends Model
{
    protected $table = 'daftar_pembelanjaan_details';

    protected $fillable = [
        'daftar_pembelanjaan_id',
        'barang_id',
        'supplier_id',
        'qty',
        'harga',
        'subtotal',
    ];

    public function daftarPembelanjaan()
    {
        return $this->belongsTo(DaftarPembelanjaan::class);
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}