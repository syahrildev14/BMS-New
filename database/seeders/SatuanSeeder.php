<?php

namespace Database\Seeders;

use App\Models\Satuan;
use Illuminate\Database\Seeder;

class SatuanSeeder extends Seeder
{
    public function run(): void
    {
        $satuans = [
            ['code' => 'KG', 'name' => 'Kilogram'],
            ['code' => 'GR', 'name' => 'Gram'],
            ['code' => 'LTR', 'name' => 'Liter'],
            ['code' => 'ML', 'name' => 'Mililiter'],
            ['code' => 'PCS', 'name' => 'Pieces'],
            ['code' => 'DUS', 'name' => 'Dus'],
            ['code' => 'KARTON', 'name' => 'Karton'],
            ['code' => 'IKAT', 'name' => 'Ikat'],
        ];

        foreach ($satuans as $satuan) {
            Satuan::updateOrCreate(['code' => $satuan['code']], $satuan);
        }
    }
}
