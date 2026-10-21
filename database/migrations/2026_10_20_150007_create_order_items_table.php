<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('item_id')
                ->constrained('items')
                ->restrictOnDelete();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();

            $table->foreignId('surat_jalan_id')
                ->nullable()
                ->constrained('surat_jalans')
                ->nullOnDelete();

            $table->boolean('is_addon')->default(false);

            $table->decimal('qty_ordered', 10, 2);
            $table->decimal('qty_received', 10, 2)->nullable();
            $table->decimal('qty_rejected', 10, 2)->nullable();
            $table->text('rejection_reason')->nullable();

            $table->decimal('estimated_price', 12, 2);
            $table->decimal('final_cost_price', 12, 2)->nullable();
            $table->decimal('final_selling_price', 12, 2)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
