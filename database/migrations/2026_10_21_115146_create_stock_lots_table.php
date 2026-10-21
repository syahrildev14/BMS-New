<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('gudang_id')
                ->constrained('gudangs')
                ->restrictOnDelete();

            $table->foreignId('item_id')
                ->constrained('items')
                ->restrictOnDelete();

            $table->foreignId('purchase_order_item_id')
                ->nullable()
                ->constrained('purchase_order_items')
                ->nullOnDelete();

            $table->decimal('qty_received', 10, 2);
            $table->decimal('qty_remaining', 10, 2);
            $table->decimal('unit_cost', 12, 2);

            $table->dateTime('received_at');

            $table->timestamps();

            $table->index(['gudang_id', 'item_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_lots');
    }
};
