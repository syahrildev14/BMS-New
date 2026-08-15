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
        Schema::create('daftar_pembelanjaan_suppliers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('daftar_pembelanjaan_id')
                ->constrained('daftar_pembelanjaans')
                ->cascadeOnDelete();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->cascadeOnDelete();

            $table->date('tanggal_pembelian');

            $table->decimal('total_harga', 15, 2)
                ->default(0);

            $table->enum('status', [
                'draft',
                'proses',
                'selesai',
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
        Schema::dropIfExists('daftar_pembelanjaan_suppliers');
    }
};
