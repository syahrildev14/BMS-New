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
        Schema::create('barangs', function (Blueprint $table) {

            $table->id();

            $table->foreignId('satuan_id')
                ->constrained('satuans')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('sku_barang')->unique();
            $table->string('nama_barang');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barangs');
    }
};
