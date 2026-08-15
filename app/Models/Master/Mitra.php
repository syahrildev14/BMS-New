<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Master\Sppg;

class Mitra extends Model
{
    protected $table = 'mitras';
    protected $fillable = [
        'nama_yayasan',
        'alamat_yayasan',
        'nama_pic_yayasan',
        'no_pic_yayasan',
    ];

    public function sppgs(): HasMany
    {
        return $this->hasMany(Sppg::class);
    }
}
