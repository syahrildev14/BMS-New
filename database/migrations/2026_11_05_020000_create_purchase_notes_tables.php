<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Nota Pembelian: the koperasi's purchase receipts.
 *  - DRY_GOODS nota: direct shopping (e.g. Saturday) → stock lots in Gudang.
 *  - WET_GOODS nota: one per Tanda Terima (Supplier + Dapur + day) → its
 *    prices become the cost price of exactly those order lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_notes', function (Blueprint $table) {
            $table->id();
            $table->string('note_number')->unique();
            $table->string('reference_number')->nullable();
            $table->enum('category', ['DRY_GOODS', 'WET_GOODS']);
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('surat_jalan_id')->nullable()->unique()->constrained('surat_jalans')->restrictOnDelete();
            $table->foreignId('dapur_id')->nullable()->constrained('dapurs')->restrictOnDelete();
            $table->date('note_date');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->boolean('has_discrepancy')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['category', 'note_date']);
        });

        Schema::create('purchase_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_note_id')->constrained('purchase_notes')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->decimal('qty', 10, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });

        Schema::table('stock_lots', function (Blueprint $table) {
            $table->foreignId('purchase_note_item_id')->nullable()->after('purchase_order_item_id')
                ->constrained('purchase_note_items')->nullOnDelete();
        });

        // Additive grant (not syncPermissions): production roles may have
        // been customised through the Role UI and must not be reset.
        $permission = Permission::findOrCreate('purchase-notes.manage', 'web');

        foreach (['purchasing', 'admin'] as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('stock_lots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_note_item_id');
        });

        Schema::dropIfExists('purchase_note_items');
        Schema::dropIfExists('purchase_notes');

        Permission::where('name', 'purchase-notes.manage')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
