<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per stock receipt into a Gudang. `qty_remaining` is decremented as
 * order fulfillment consumes it FIFO (oldest `received_at` first) — see
 * StockService. Source of truth for stock on hand: SUM(qty_remaining)
 * grouped by gudang_id + item_id, not a separate balance table.
 */
class StockLot extends Model
{
    use HasFactory;

    protected $fillable = [
        'gudang_id',
        'item_id',
        'purchase_order_item_id',
        'qty_received',
        'qty_remaining',
        'unit_cost',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'qty_received' => 'decimal:2',
            'qty_remaining' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
