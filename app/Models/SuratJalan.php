<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Delivery document. SURAT_JALAN = dry goods shipped by the koperasi;
 * TANDA_TERIMA = wet goods delivered straight by the Supplier. One document
 * per Dapur per shipment date covering every supplier (supplier_id is NULL;
 * older documents have one), and it may cover order_items from several Orders —
 * order_id is legacy (documents created before 2026-11-05 only).
 */
class SuratJalan extends Model
{
    use HasFactory;

    public const SURAT_JALAN = 'SURAT_JALAN';

    public const TANDA_TERIMA = 'TANDA_TERIMA';

    public const VERIFICATION_PENDING = 'PENDING';

    public const VERIFICATION_VERIFIED = 'VERIFIED';

    public const VERIFICATION_DISCREPANCY = 'DISCREPANCY';

    protected $fillable = [
        'sj_number',
        'document_type',
        'order_id',
        'dapur_id',
        'supplier_id',
        'shipment_date',
        'type',
        'sequence_number',
        'pdf_url',
        'tanda_terima_pdf_url',
        'verification_status',
        'verification_notes',
        'verified_by',
        'verified_at',
        'printed_by',
        'document_template_id',
        'armada_name',
        'plate_number',
        'driver_name',
    ];

    protected function casts(): array
    {
        return [
            'shipment_date' => 'date:Y-m-d',
            'verified_at' => 'datetime',
        ];
    }

    /** Legacy single-Order link; use orders() for documents spanning several Orders. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_items', 'surat_jalan_id', 'order_id')->distinct();
    }

    public function dapur(): BelongsTo
    {
        return $this->belongsTo(Dapur::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(DeliveryDocumentPhoto::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** Wet-goods Nota Pembelian for this Tanda Terima: one per supplier on it. */
    public function purchaseNotes(): HasMany
    {
        return $this->hasMany(PurchaseNote::class);
    }

    /**
     * Documents issued since per-supplier documents (2026-11-05) have no
     * order_id; only those take part in the Crosscheck H+1.
     */
    public function scopeCrosscheckable($query)
    {
        return $query->whereNull('order_id');
    }

    public function isCrosscheckable(): bool
    {
        return $this->order_id === null;
    }
}
