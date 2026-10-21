<?php

namespace Database\Seeders;

use App\Models\Gudang;
use Illuminate\Database\Seeder;

/**
 * Hanya satu gudang pusat untuk semua Dapur saat ini (keputusan Boss,
 * 2026-10-21) — model tetap disiapkan untuk multi-lokasi di masa depan.
 */
class GudangSeeder extends Seeder
{
    public function run(): void
    {
        Gudang::updateOrCreate(
            ['code' => 'GDG-PUSAT'],
            ['name' => 'Gudang Pusat', 'address' => null]
        );
    }
}
