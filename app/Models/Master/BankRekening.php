<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankRekening extends Model
{
    protected $fillable = [
        'nama_bank',
        'no_rekening',
        'atas_nama_rekening',
        'cabang_bank',
    ];
}
