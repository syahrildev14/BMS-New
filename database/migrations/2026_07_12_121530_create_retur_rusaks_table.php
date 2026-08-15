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
        Schema::create('retur_rusaks', function (Blueprint $table) {

            $table->id();

            $table->foreignId('inbound_id')
                ->constrained('inbounds')
                ->cascadeOnDelete();

            $table->date('tanggal');

            $table->enum('jenis', [
                'retur',
                'rusak'
            ]);

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
        Schema::dropIfExists('retur_rusaks');
    }
};
