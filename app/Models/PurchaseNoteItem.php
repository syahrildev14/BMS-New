<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseNoteItem extends Model
{
    protected $fillable = [
        'purchase_note_id',
        'item_id',
        'order_item_id',
        'qty',
        'unit_price',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function purchaseNote(): BelongsTo
    {
        return $this->belongsTo(PurchaseNote::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** Gudang stock lot created from this line (DRY_GOODS notes only). */
    public function stockLot(): HasOne
    {
        return $this->hasOne(StockLot::class);
    }
}
