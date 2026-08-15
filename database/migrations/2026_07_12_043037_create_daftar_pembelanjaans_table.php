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
        Schema::create('daftar_pembelanjaans', function (Blueprint $table) {

            $table->id();

            $table->foreignId('list_order_penawaran_id')
                ->constrained('list_order_penawarans')
                ->cascadeOnDelete();

            $table->date('tanggal_pesan');

            $table->decimal('total_harga', 15, 2)->default(0);

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
        Schema::dropIfExists('daftar_pembelanjaans');
    }
};
