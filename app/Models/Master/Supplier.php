<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Models\Pembelian\DaftarPembelanjaanDetail;
use App\Models\Pembelian\DaftarPembelanjaanSupplier;

class Supplier extends Model
{

    protected $table = 'suppliers';


    protected $fillable = [
        'nama_supplier',
        'alamat_supplier',
        'nomor_telepon_supplier',
        'kategori'
    ];

    public function daftarPembelanjaanDetails()
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
