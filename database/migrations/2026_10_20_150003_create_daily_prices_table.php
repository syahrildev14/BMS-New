<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_prices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('item_id')
                ->constrained('items')
                ->cascadeOnDelete();

            $table->date('date');

            $table->decimal('cost_price', 12, 2);
            $table->decimal('calculated_price', 12, 2);

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(['item_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_prices');
    }
};
