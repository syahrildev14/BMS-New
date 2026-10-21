<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->string('order_number')->unique();

            $table->foreignId('dapur_id')
                ->constrained('dapurs')
                ->restrictOnDelete();

            $table->date('delivery_date');

            $table->enum('status', [
                'DRAFT',
                'SUBMITTED',
                'LOCKED',
                'PROCESSING',
                'DELIVERED',
                'COMPLETED',
                'CANCELLED',
            ])->default('DRAFT');

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
