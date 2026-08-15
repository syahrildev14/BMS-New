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
        Schema::create('retur_rusak_details', function (Blueprint $table) {

            $table->id();

            $table->foreignId('retur_rusak_id')
                ->constrained('retur_rusaks')
                ->cascadeOnDelete();

            $table->foreignId('barang_id')
                ->constrained('barangs')
                ->cascadeOnDelete();

            $table->decimal('qty', 12, 2);

            $table->decimal('harga', 15, 2);

            $table->decimal('subtotal', 15, 2);

            $table->text('alasan')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retur_rusak_details');
    }
};
