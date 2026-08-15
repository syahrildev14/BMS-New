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
        Schema::create('list_order_penawaran_details', function (Blueprint $table) {

            $table->id();

            $table->foreignId('list_order_penawaran_id')
                ->constrained('list_order_penawarans')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('barang_id')
                ->constrained('barangs')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->decimal('qty', 12, 2);

            $table->text('keterangan')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_order_penawaran_details');
    }
};
