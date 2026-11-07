<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'dapur_id',
        'delivery_date',
        'status',
        'notes',
        'pdf_url',
        'tanda_terima_pdf_url',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date:Y-m-d',
        ];
    }

    public function dapur(): BelongsTo
    {
        return $this->belongsTo(Dapur::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Delivery documents (SJ kering / TT basah) carrying any of this Order's
     * items. Resolved through order_items because one document may cover
     * several Orders of the same Dapur.
     */
    public function suratJalans(): BelongsToMany
    {
        return $this->belongsToMany(SuratJalan::class, 'order_items', 'order_id', 'surat_jalan_id')->distinct();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function supplierBills(): HasMany
    {
        return $this->hasMany(SupplierBill::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'triggered_by_order_id');
    }
}
