<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            $table->string('po_number')->unique();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();

            $table->foreignId('gudang_id')
                ->constrained('gudangs')
                ->restrictOnDelete();

            $table->foreignId('triggered_by_order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            $table->enum('status', [
                'DRAFT',
                'SENT',
                'PARTIALLY_RECEIVED',
                'RECEIVED',
                'CANCELLED',
            ])->default('DRAFT');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('sent_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('sent_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
