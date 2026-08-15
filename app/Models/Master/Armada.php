<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Armada extends Model
{
    protected $fillable = [
        'nama_unit',
        'no_polisi',
        'jenis_kendaraan',
    ];
}
