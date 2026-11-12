<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Tanda Terima now covers every supplier delivering to one Dapur on one
 * day, so a wet nota is one per (Tanda Terima, Supplier) instead of one per
 * Tanda Terima. The composite index is added first: it also serves the
 * surat_jalan_id foreign key, so MySQL lets the old unique index go.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_notes', function (Blueprint $table) {
            $table->unique(['surat_jalan_id', 'supplier_id']);
        });

        Schema::table('purchase_notes', function (Blueprint $table) {
            $table->dropUnique(['surat_jalan_id']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_notes', function (Blueprint $table) {
            $table->unique(['surat_jalan_id']);
        });

        Schema::table('purchase_notes', function (Blueprint $table) {
            $table->dropUnique(['surat_jalan_id', 'supplier_id']);
        });
    }
};
