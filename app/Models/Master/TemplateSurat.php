<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateSurat extends Model
{
    protected $fillable = [
        'nama_template',
        'jenis_template',
    ];
}