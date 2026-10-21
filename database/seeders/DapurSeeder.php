<?php

namespace Database\Seeders;

use App\Models\Dapur;
use Illuminate\Database\Seeder;

class DapurSeeder extends Seeder
{
    public function run(): void
    {
        $dapurs = [
            ['code' => 'SPPG-001', 'name' => 'Dapur SPPG Cempaka Putih', 'address' => 'Jl. Cempaka Putih Raya No. 10, Jakarta Pusat', 'pic_name' => 'Dewi Lestari', 'pic_phone' => '081298700001'],
            ['code' => 'SPPG-002', 'name' => 'Dapur SPPG Kebon Jeruk', 'address' => 'Jl. Kebon Jeruk Raya No. 25, Jakarta Barat', 'pic_name' => 'Muhammad Rizky', 'pic_phone' => '081298700002'],
            ['code' => 'SPPG-003', 'name' => 'Dapur SPPG Cipayung', 'address' => 'Jl. Cipayung Raya No. 7, Jakarta Timur', 'pic_name' => 'Nur Hidayah', 'pic_phone' => '081298700003'],
            ['code' => 'SPPG-004', 'name' => 'Dapur SPPG Pasar Minggu', 'address' => 'Jl. Pasar Minggu No. 15, Jakarta Selatan', 'pic_name' => 'Yusuf Ramadhan', 'pic_phone' => '081298700004'],
            ['code' => 'SPPG-005', 'name' => 'Dapur SPPG Penjaringan', 'address' => 'Jl. Penjaringan Raya No. 3, Jakarta Utara', 'pic_name' => 'Fitriani Maharani', 'pic_phone' => '081298700005'],
        ];

        foreach ($dapurs as $dapur) {
            Dapur::updateOrCreate(['code' => $dapur['code']], $dapur);
        }
    }
}
