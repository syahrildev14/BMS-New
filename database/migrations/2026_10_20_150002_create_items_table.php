<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();

            $table->string('sku')->unique();
            $table->string('name');

            $table->enum('category', ['DRY_GOODS', 'WET_GOODS']);

            $table->foreignId('satuan_id')
                ->constrained('satuans')
                ->restrictOnDelete();

            $table->foreignId('default_supplier_id')
                ->nullable()
                ->constrained('suppliers')
                ->nullOnDelete();

            $table->enum('margin_type', ['PERCENTAGE', 'FIXED_IDR']);
            $table->decimal('margin_value', 10, 2);

            $table->decimal('base_cost_price', 12, 2)->default(0);
            $table->decimal('base_selling_price', 12, 2)->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
