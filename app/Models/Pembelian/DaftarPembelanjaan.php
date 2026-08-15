<?php

namespace App\Models\Pembelian;

use Illuminate\Database\Eloquent\Model;
use App\Models\Pembelian\ListOrderPenawaran;
use App\Models\Pembelian\DaftarPembelanjaanDetail;
use App\Models\Pembelian\DaftarPembelanjaanSupplier;

class DaftarPembelanjaan extends Model
{
    protected $table = 'daftar_pembelanjaans';

    protected $fillable = [
        'list_order_penawaran_id',
        'tanggal_pesan',
        'total_harga',
        'status',
        'keterangan',
    ];

    public function listOrderPenawaran()
    {
        return $this->belongsTo(ListOrderPenawaran::class);
    }

    public function details()
    {
        return $this->hasMany(DaftarPembelanjaanDetail::class);
    }
    public function daftarPembelanjaanSuppliers()
    {
        return $this->hasMany(
            DaftarPembelanjaanSupplier::class
        );
    }
}
