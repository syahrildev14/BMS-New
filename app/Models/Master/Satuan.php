<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class Satuan extends Model
{
    protected $table = 'satuans';

    protected $fillable = [
        'kode_satuan',
        'nama_satuan',
    ];


    public function barangs()
    {
        return $this->hasMany(Barang::class);
    }
}
