<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nota Pembelian. DRY_GOODS: direct shopping → Gudang stock. WET_GOODS: one
 * per Tanda Terima (surat_jalan_id) → prices the order lines on it.
 */
class PurchaseNote extends Model
{
    protected $fillable = [
        'note_number',
        'reference_number',
        'category',
        'supplier_id',
        'surat_jalan_id',
        'dapur_id',
        'note_date',
        'total_amount',
        'has_discrepancy',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'note_date' => 'date:Y-m-d',
            'total_amount' => 'decimal:2',
            'has_discrepancy' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseNoteItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function suratJalan(): BelongsTo
    {
        return $this->belongsTo(SuratJalan::class);
    }

    public function dapur(): BelongsTo
    {
        return $this->belongsTo(Dapur::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
