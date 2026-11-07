<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Arrival photos uploaded by Dapur and the koperasi's H+1 crosscheck. A
 * document must be VERIFIED before its Order can be invoiced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_jalans', function (Blueprint $table) {
            $table->enum('verification_status', ['PENDING', 'VERIFIED', 'DISCREPANCY'])->default('PENDING')->after('tanda_terima_pdf_url');
            $table->text('verification_notes')->nullable()->after('verification_status');
            $table->foreignId('verified_by')->nullable()->after('verification_notes')->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable()->after('verified_by');

            $table->index('verification_status');
        });

        Schema::create('delivery_document_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_jalan_id')->constrained('surat_jalans')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // Additive grant (not syncPermissions): production roles may have
        // been customised through the Role UI and must not be reset.
        $permission = Permission::findOrCreate('delivery-verifications.manage', 'web');

        foreach (['ops', 'admin'] as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_document_photos');

        Schema::table('surat_jalans', function (Blueprint $table) {
            $table->dropIndex(['verification_status']);
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['verification_status', 'verification_notes', 'verified_at']);
        });

        Permission::where('name', 'delivery-verifications.manage')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
