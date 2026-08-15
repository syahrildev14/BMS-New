<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_gudangs', function (Blueprint $table) {

            $table->id();

            $table->foreignId('barang_id')
                ->constrained('barangs')
                ->cascadeOnDelete();

            $table->foreignId('gudang_id')
                ->constrained('gudangs')
                ->cascadeOnDelete();

            $table->decimal('qty',12,2)
                ->default(0);

            $table->decimal('harga_rata_rata',15,2)
                ->default(0);

            $table->timestamps();

            $table->unique([
                'barang_id',
                'gudang_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_gudangs');
    }
};