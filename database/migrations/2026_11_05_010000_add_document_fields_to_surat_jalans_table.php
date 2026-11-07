<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * surat_jalans becomes a generic delivery document:
 *  - SURAT_JALAN  — dry goods shipped by the koperasi, one per Dapur+Supplier,
 *                   may cover several daily Orders (weekly Sunday shipment).
 *  - TANDA_TERIMA — wet goods delivered straight by the Supplier, one per
 *                   Dapur+Supplier per delivery date.
 * order_items.surat_jalan_id stays the item → document link, so a document
 * no longer belongs to a single Order (order_id becomes nullable, legacy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_jalans', function (Blueprint $table) {
            $table->enum('document_type', ['SURAT_JALAN', 'TANDA_TERIMA'])->default('SURAT_JALAN')->after('sj_number');
            $table->foreignId('dapur_id')->nullable()->after('order_id')->constrained('dapurs')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->after('dapur_id')->constrained('suppliers')->restrictOnDelete();
            $table->date('shipment_date')->nullable()->after('supplier_id');
            $table->foreignId('order_id')->nullable()->change();

            $table->index(['dapur_id', 'document_type', 'shipment_date']);
        });

        DB::table('surat_jalans')->whereNotNull('order_id')->update([
            'dapur_id' => DB::raw('(SELECT dapur_id FROM orders WHERE orders.id = surat_jalans.order_id)'),
            'shipment_date' => DB::raw('(SELECT delivery_date FROM orders WHERE orders.id = surat_jalans.order_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('surat_jalans', function (Blueprint $table) {
            // Foreign keys first: MySQL refuses to drop an index a foreign key
            // still relies on. order_id stays nullable, new documents have none.
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['dapur_id']);
            $table->dropIndex(['dapur_id', 'document_type', 'shipment_date']);
            $table->dropColumn(['document_type', 'dapur_id', 'supplier_id', 'shipment_date']);
        });
    }
};
