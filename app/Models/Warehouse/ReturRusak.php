<?php

namespace App\Models\Warehouse;

use Illuminate\Database\Eloquent\Model;
use App\Models\Warehouse\ReturRusakDetail;

class ReturRusak extends Model
{
    protected $fillable = [
        'inbound_id',
        'tanggal',
        'jenis',
        'status',
        'keterangan',
    ];

    public function inbound()
    {
        return $this->belongsTo(Inbound::class);
    }

    public function details()
    {
        return $this->hasMany(ReturRusakDetail::class);
    }
}
