<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'category',
        'satuan_id',
        'default_supplier_id',
        'margin_type',
        'margin_value',
        'base_cost_price',
        'base_selling_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'margin_value' => 'decimal:2',
            'base_cost_price' => 'decimal:2',
            'base_selling_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function defaultSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id');
    }

    public function satuan(): BelongsTo
    {
        return $this->belongsTo(Satuan::class);
    }

    public function dailyPrices(): HasMany
    {
        return $this->hasMany(DailyPrice::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stockLots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Apply this item's own margin to an arbitrary cost — used by both
     * PricingEngineService (WET_GOODS daily price) and StockService
     * (DRY_GOODS FIFO-consumed cost) so the margin formula only lives once.
     */
    public function calculateSellingPrice(float $cost): float
    {
        return $this->margin_type === 'PERCENTAGE'
            ? $cost + ($cost * ((float) $this->margin_value / 100))
            : $cost + (float) $this->margin_value;
    }
}
