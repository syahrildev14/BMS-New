<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penjualans', function (Blueprint $table) {

            $table->id();

            $table->string('nomor_penjualan')->unique();

            $table->foreignId('list_order_penawaran_id')
                ->constrained('list_order_penawarans')
                ->cascadeOnDelete();

            $table->foreignId('gudang_id')
                ->constrained('gudangs')
                ->cascadeOnDelete();

            $table->date('tanggal');

            $table->enum('status', [
                'draft',
                'selesai',
            ])->default('draft');

            $table->text('keterangan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penjualans');
    }
};