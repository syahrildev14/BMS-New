<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'order_id',
        'dapur_id',
        'supplier_id',
        'total_amount',
        'status',
        'pdf_url',
        'due_date',
        'paid_at',
        'payment_method',
        'payment_reference',
        'document_template_id',
        'bank_name',
        'bank_account',
        'bank_owner',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'due_date' => 'date:Y-m-d',
            'paid_at' => 'datetime',
            'issued_at' => 'date:Y-m-d',
        ];
    }

    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }

    /**
     * Earlier PAID invoices of this (order, supplier). An additional invoice's
     * line list repeats what these already covered, so its PDF subtracts them.
     *
     * @return Collection<int, Invoice>
     */
    public function earlierPaidInvoices(): Collection
    {
        return self::where('order_id', $this->order_id)
            ->where('supplier_id', $this->supplier_id)
            ->where('status', 'PAID')
            ->where('id', '<', $this->id)
            ->orderBy('id')
            ->get();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function dapur(): BelongsTo
    {
        return $this->belongsTo(Dapur::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
