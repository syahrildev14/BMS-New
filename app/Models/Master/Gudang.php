<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Warehouse\Inbound;
use App\Models\Warehouse\StokGudang;
use App\Models\Penjualan\Penjualan;

class Gudang extends Model
{

    protected $fillable = [
        'nama_gudang',
        'alamat_gudang',
        'nama_pic',
        'no_telepon_pic',
    ];

    public function inbounds()
    {
        return $this->hasMany(
            Inbound::class
        );
    }
    public function stokGudangs()
    {
        return $this->hasMany(
            StokGudang::class
        );
    }
    public function penjualans()
    {
        return $this->hasMany(Penjualan::class);
    }
}
