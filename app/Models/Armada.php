<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Armada extends Model
{
    protected $fillable = ['name', 'plate_number', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
