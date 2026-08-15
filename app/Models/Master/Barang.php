<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Models\Pembelian\ListOrderPenawaranDetail;
use App\Models\Pembelian\DaftarPembelanjaanDetail;
use App\Models\Pembelian\DaftarPembelanjaanSupplierDetail;
use App\Models\Warehouse\InboundDetail;
use App\Models\Warehouse\StokGudang;
use App\Models\Warehouse\ReturRusakDetail;
use App\Models\Penjualan\PenjualanDetail;

class Barang extends Model
{
    protected $table = 'barangs';

    protected $fillable = [
        'sku_barang',
        'nama_barang',
        'satuan_id',
    ];

    public function satuan()
    {
        return $this->belongsTo(Satuan::class);
    }

    public function listOrderDetails()
    {
        return $this->hasMany(ListOrderPenawaranDetail::class);
    }
    public function daftarPembelanjaanDetails()
    {
        return $this->hasMany(DaftarPembelanjaanDetail::class);
    }
    public function daftarPembelanjaanSupplierDetails()
    {
        return $this->hasMany(
            DaftarPembelanjaanSupplierDetail::class
        );
    }
    public function inboundDetails()
    {
        return $this->hasMany(
            InboundDetail::class
        );
    }
    public function stokGudangs()
    {
        return $this->hasMany(
            StokGudang::class
        );
    }
    public function returRusakDetails()
    {
        return $this->hasMany(
            ReturRusakDetail::class
        );
    }
    public function penjualanDetails()
    {
        return $this->hasMany(PenjualanDetail::class);
    }
}
