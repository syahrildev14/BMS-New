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
        Schema::create('tanda_terimas', function (Blueprint $table) {

            $table->id();

            $table->foreignId('penjualan_id')
                ->constrained('penjualans')
                ->cascadeOnDelete();

            $table->date('tanggal');

            $table->string('nama_penerima');

            $table->string('jabatan')->nullable();

            $table->text('catatan')->nullable();

            $table->timestamps();

            $table->unique('penjualan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tanda_terimas');
    }
};
