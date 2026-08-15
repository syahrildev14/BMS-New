<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'nama_role',
    ];

    public function karyawans(): HasMany
    {
        return $this->hasMany(Karyawan::class);
    }
}