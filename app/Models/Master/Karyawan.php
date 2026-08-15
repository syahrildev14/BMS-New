<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Karyawan extends Model
{
    protected $fillable = [
        'nama_karyawan',
        'alamat_karyawan',
        'no_telepon_karyawan',
        'jabatan_karyawan',
        'tanggal_masuk',
        'status',
        'role_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_masuk' => 'date',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}