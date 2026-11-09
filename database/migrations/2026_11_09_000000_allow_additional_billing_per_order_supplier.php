<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A late shipment on a PAID invoice or bill goes on an additional one, so an
 * (order, supplier) pair can now have several. The plain index is added first
 * because MySQL's foreign key on order_id relies on the unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoices', 'supplier_bills'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->index(['order_id', 'supplier_id']);
            });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropUnique(['order_id', 'supplier_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'supplier_bills'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unique(['order_id', 'supplier_id']);
            });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropIndex(['order_id', 'supplier_id']);
            });
        }
    }
};
