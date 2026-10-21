<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Satuan;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $satuans = Satuan::pluck('id', 'code');
        $suppliers = Supplier::pluck('id', 'code');

        $items = [
            // DRY_GOODS — harga relatif stabil
            ['sku' => 'BRG-001', 'name' => 'Beras Premium', 'category' => 'DRY_GOODS', 'satuan' => 'KG', 'supplier' => 'SUP-BERAS', 'margin_type' => 'PERCENTAGE', 'margin_value' => 10, 'cost' => 11000],
            ['sku' => 'BRG-002', 'name' => 'Gula Pasir', 'category' => 'DRY_GOODS', 'satuan' => 'KG', 'supplier' => 'SUP-BUMBU', 'margin_type' => 'PERCENTAGE', 'margin_value' => 8, 'cost' => 14000],
            ['sku' => 'BRG-003', 'name' => 'Tepung Terigu', 'category' => 'DRY_GOODS', 'satuan' => 'KG', 'supplier' => 'SUP-BUMBU', 'margin_type' => 'PERCENTAGE', 'margin_value' => 8, 'cost' => 10000],
            ['sku' => 'BRG-004', 'name' => 'Minyak Goreng', 'category' => 'DRY_GOODS', 'satuan' => 'LTR', 'supplier' => 'SUP-MINYAK', 'margin_type' => 'PERCENTAGE', 'margin_value' => 7, 'cost' => 15000],
            ['sku' => 'BRG-005', 'name' => 'Garam Dapur', 'category' => 'DRY_GOODS', 'satuan' => 'KG', 'supplier' => 'SUP-BUMBU', 'margin_type' => 'FIXED_IDR', 'margin_value' => 500, 'cost' => 5000],
            ['sku' => 'BRG-006', 'name' => 'Kecap Manis', 'category' => 'DRY_GOODS', 'satuan' => 'LTR', 'supplier' => 'SUP-BUMBU', 'margin_type' => 'PERCENTAGE', 'margin_value' => 12, 'cost' => 18000],
            ['sku' => 'BRG-007', 'name' => 'Mie Instan', 'category' => 'DRY_GOODS', 'satuan' => 'DUS', 'supplier' => 'SUP-BUMBU', 'margin_type' => 'PERCENTAGE', 'margin_value' => 10, 'cost' => 85000],
            ['sku' => 'BRG-008', 'name' => 'Susu UHT', 'category' => 'DRY_GOODS', 'satuan' => 'DUS', 'supplier' => 'SUP-MINYAK', 'margin_type' => 'PERCENTAGE', 'margin_value' => 9, 'cost' => 95000],

            // WET_GOODS — fluktuasi harga harian (Module 2)
            ['sku' => 'BRG-009', 'name' => 'Ayam Potong', 'category' => 'WET_GOODS', 'satuan' => 'KG', 'supplier' => 'SUP-PROTEIN', 'margin_type' => 'PERCENTAGE', 'margin_value' => 12, 'cost' => 32000],
            ['sku' => 'BRG-010', 'name' => 'Telur Ayam', 'category' => 'WET_GOODS', 'satuan' => 'KG', 'supplier' => 'SUP-PROTEIN', 'margin_type' => 'PERCENTAGE', 'margin_value' => 10, 'cost' => 27000],
            ['sku' => 'BRG-011', 'name' => 'Daging Sapi', 'category' => 'WET_GOODS', 'satuan' => 'KG', 'supplier' => 'SUP-PROTEIN', 'margin_type' => 'PERCENTAGE', 'margin_value' => 10, 'cost' => 120000],
            ['sku' => 'BRG-012', 'name' => 'Tahu', 'category' => 'WET_GOODS', 'satuan' => 'PCS', 'supplier' => 'SUP-PROTEIN', 'margin_type' => 'FIXED_IDR', 'margin_value' => 200, 'cost' => 1000],
            ['sku' => 'BRG-013', 'name' => 'Tempe', 'category' => 'WET_GOODS', 'satuan' => 'PCS', 'supplier' => 'SUP-PROTEIN', 'margin_type' => 'FIXED_IDR', 'margin_value' => 200, 'cost' => 1200],
            ['sku' => 'BRG-014', 'name' => 'Bawang Merah', 'category' => 'WET_GOODS', 'satuan' => 'KG', 'supplier' => 'SUP-SAYUR', 'margin_type' => 'PERCENTAGE', 'margin_value' => 15, 'cost' => 28000],
            ['sku' => 'BRG-015', 'name' => 'Bawang Putih', 'category' => 'WET_GOODS', 'satuan' => 'KG', 'supplier' => 'SUP-SAYUR', 'margin_type' => 'PERCENTAGE', 'margin_value' => 15, 'cost' => 32000],
            ['sku' => 'BRG-016', 'name' => 'Cabai Merah', 'category' => 'WET_GOODS', 'satuan' => 'KG', 'supplier' => 'SUP-SAYUR', 'margin_type' => 'PERCENTAGE', 'margin_value' => 20, 'cost' => 40000],
            ['sku' => 'BRG-017', 'name' => 'Wortel', 'category' => 'WET_GOODS', 'satuan' => 'KG', 'supplier' => 'SUP-SAYUR', 'margin_type' => 'PERCENTAGE', 'margin_value' => 15, 'cost' => 12000],
            ['sku' => 'BRG-018', 'name' => 'Bayam', 'category' => 'WET_GOODS', 'satuan' => 'IKAT', 'supplier' => 'SUP-SAYUR', 'margin_type' => 'PERCENTAGE', 'margin_value' => 15, 'cost' => 3000],
        ];

        foreach ($items as $item) {
            $sellingPrice = $item['margin_type'] === 'PERCENTAGE'
                ? $item['cost'] + ($item['cost'] * ($item['margin_value'] / 100))
                : $item['cost'] + $item['margin_value'];

            Item::updateOrCreate(
                ['sku' => $item['sku']],
                [
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'satuan_id' => $satuans[$item['satuan']],
                    'default_supplier_id' => $suppliers[$item['supplier']],
                    'margin_type' => $item['margin_type'],
                    'margin_value' => $item['margin_value'],
                    'base_cost_price' => $item['cost'],
                    'base_selling_price' => $sellingPrice,
                    'is_active' => true,
                ]
            );
        }
    }
}
