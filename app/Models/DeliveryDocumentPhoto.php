<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Arrival photo a Dapur uploaded for one delivery document. */
class DeliveryDocumentPhoto extends Model
{
    protected $fillable = [
        'surat_jalan_id',
        'path',
        'original_name',
        'uploaded_by',
    ];

    public function suratJalan(): BelongsTo
    {
        return $this->belongsTo(SuratJalan::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
