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
        Schema::create('list_order_penawarans', function (Blueprint $table) {
            $table->id();

            $table->string('nomor_order')->unique();

            $table->date('tanggal_pesan');

            $table->date('tanggal_kirim');

            $table->foreignId('sppg_id')
                ->constrained('sppgs')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->text('keterangan')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_order_penawarans');
    }
};
