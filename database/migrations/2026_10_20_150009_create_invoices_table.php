<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->string('invoice_number')->unique();

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

            $table->string('pdf_url')->nullable();

            $table->timestamps();

            $table->unique(['order_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
