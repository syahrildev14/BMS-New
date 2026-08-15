<?php

namespace App\Models\Penjualan;

use Illuminate\Database\Eloquent\Model;
use App\Models\Master\Gudang;
use App\Models\Pembelian\ListOrderPenawaran;
use App\Models\Penjualan\PenjualanDetail;
use App\Models\Penjualan\TandaTerima;

class Penjualan extends Model
{
    protected $fillable = [
        'nomor_penjualan',
        'list_order_penawaran_id',
        'gudang_id',
        'tanggal',
        'status',
        'keterangan',
    ];

    public function listOrderPenawaran()
    {
        return $this->belongsTo(ListOrderPenawaran::class);
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class);
    }

    public function details()
    {
        return $this->hasMany(PenjualanDetail::class);
    }
    public function tandaTerima()
    {
        return $this->hasOne(TandaTerima::class);
    }
}
