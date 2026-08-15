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
        Schema::create('inbounds', function (Blueprint $table) {

            $table->id();

            $table->foreignId('daftar_pembelanjaan_supplier_id')
                ->constrained('daftar_pembelanjaan_suppliers')
                ->cascadeOnDelete();

            $table->foreignId('gudang_id')
                ->constrained('gudangs')
                ->cascadeOnDelete();

            $table->date('tanggal_masuk');

            $table->enum('status', [
                'draft',
                'selesai'
            ])->default('draft');

            $table->text('keterangan')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbounds');
    }
};
