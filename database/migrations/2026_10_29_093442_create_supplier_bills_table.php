<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accounts Payable mirror of `invoices`: what BMS owes the Supplier for an
     * Order, split the same way (one row per order_id+supplier_id), reconciled
     * from the same order_items QC data — just cost_price instead of
     * selling_price. Covers both WET_GOODS (shipped direct from Supplier,
     * final_cost_price from the daily price) and DRY_GOODS (shipped via
     * Gudang, final_cost_price from FIFO-consumed stock lot cost) uniformly,
     * since both already land on order_items.final_cost_price.
     */
    public function up(): void
    {
        Schema::create('supplier_bills', function (Blueprint $table) {
            $table->id();

            $table->string('bill_number')->unique();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('dapur_id')
                ->constrained('dapurs')
                ->restrictOnDelete();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();

            $table->decimal('total_amount', 12, 2)->default(0);

            $table->enum('status', [
                'DRAFT',
                'ISSUED',
                'PAID',
                'OVERDUE',
                'VOID',
            ])->default('DRAFT');

            $table->date('due_date')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();

            $table->timestamps();

            $table->unique(['order_id', 'supplier_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_bills');
    }
};
