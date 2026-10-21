<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'item_id',
        'supplier_id',
        'surat_jalan_id',
        'is_addon',
        'qty_ordered',
        'qty_received',
        'qty_rejected',
        'rejection_reason',
        'estimated_price',
        'final_cost_price',
        'final_selling_price',
    ];

    protected function casts(): array
    {
        return [
            'is_addon' => 'boolean',
            'qty_ordered' => 'decimal:2',
            'qty_received' => 'decimal:2',
            'qty_rejected' => 'decimal:2',
            'estimated_price' => 'decimal:2',
            'final_cost_price' => 'decimal:2',
            'final_selling_price' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function suratJalan(): BelongsTo
    {
        return $this->belongsTo(SuratJalan::class);
    }
}
