<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dry-goods shipping and the Daftar Belanja require one Gudang row. Production
 * only runs RoleAndPermissionSeeder and there is no Gudang master UI, so a
 * fresh production DB would otherwise have none. Same code/name as GudangSeeder
 * so local seeding stays idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('gudangs')->exists()) {
            return;
        }

        DB::table('gudangs')->insert([
            'code' => 'GDG-PUSAT',
            'name' => 'Gudang Pusat',
            'address' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Data migration: rows may already be referenced by stock lots / POs.
    }
};
