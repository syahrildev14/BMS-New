<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['code' => 'SUP-BERAS', 'name' => 'CV Sumber Beras Makmur', 'address' => 'Jl. Raya Karawang KM 5, Karawang', 'pic_name' => 'Hendra Wijaya', 'pic_phone' => '081234500001'],
            ['code' => 'SUP-PROTEIN', 'name' => 'UD Mitra Protein Segar', 'address' => 'Jl. Pasar Induk Kemayoran, Jakarta', 'pic_name' => 'Siti Aminah', 'pic_phone' => '081234500002'],
            ['code' => 'SUP-SAYUR', 'name' => 'CV Tani Sayur Nusantara', 'address' => 'Jl. Puncak Raya No. 12, Bogor', 'pic_name' => 'Agus Setiawan', 'pic_phone' => '081234500003'],
            ['code' => 'SUP-BUMBU', 'name' => 'UD Rempah Jaya Abadi', 'address' => 'Jl. Pasar Cibinong No. 8, Bogor', 'pic_name' => 'Rina Kartika', 'pic_phone' => '081234500004'],
            ['code' => 'SUP-MINYAK', 'name' => 'PT Minyak Nusantara Sejahtera', 'address' => 'Jl. Industri Raya No. 20, Bekasi', 'pic_name' => 'Budi Santoso', 'pic_phone' => '081234500005'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::updateOrCreate(['code' => $supplier['code']], $supplier);
        }
    }
}
