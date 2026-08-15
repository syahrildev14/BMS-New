<?php

namespace App\Models\Pembelian;

use Illuminate\Database\Eloquent\Model;
use App\Models\Master\Sppg;
use App\Models\Penjualan\Penjualan;

class ListOrderPenawaran extends Model
{
    protected $fillable = [
        'nomor_order',
        'tanggal_pesan',
        'tanggal_kirim',
        'sppg_id',
        'keterangan',
    ];

    public function sppg()
    {
        return $this->belongsTo(Sppg::class);
    }

    public function details()
    {
        return $this->hasMany(ListOrderPenawaranDetail::class);
    }
    public function daftarPembelanjaan()
    {
        return $this->hasOne(DaftarPembelanjaan::class);
    }
    public function penjualans()
    {
        return $this->hasMany(Penjualan::class);
    }
}
