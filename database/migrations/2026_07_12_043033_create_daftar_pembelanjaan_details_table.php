<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daftar_pembelanjaan_details', function (Blueprint $table) {

            $table->id();

            $table->foreignId('daftar_pembelanjaan_id')
                ->constrained('daftar_pembelanjaans')
                ->cascadeOnDelete();

            $table->foreignId('barang_id')
                ->constrained('barangs')
                ->cascadeOnDelete();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->cascadeOnDelete();

            $table->decimal('qty', 12, 2);

            $table->decimal('harga', 15, 2);

            $table->decimal('subtotal', 15, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daftar_pembelanjaan_details');
    }
};
