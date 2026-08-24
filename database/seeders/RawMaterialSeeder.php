<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RawMaterialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            ['name' => 'Gram', 'symbol' => 'gr'],
            ['name' => 'Mililiter', 'symbol' => 'ml'],
            ['name' => 'Pcs', 'symbol' => 'pcs'],
        ];

        foreach ($units as $unit) {
            \DB::table('units')->updateOrInsert(
                ['symbol' => $unit['symbol']],
                ['name' => $unit['name'], 'updated_at' => now(), 'created_at' => now()]
            );
        }

        $grId = \DB::table('units')->where('symbol', 'gr')->value('id');
        $mlId = \DB::table('units')->where('symbol', 'ml')->value('id');
        $pcsId = \DB::table('units')->where('symbol', 'pcs')->value('id');

        $materials = [
            ['name' => 'Biji Kopi Arabica', 'unit_id' => $grId, 'stock' => 5000, 'min_stock' => 1000],
            ['name' => 'Susu Cair', 'unit_id' => $mlId, 'stock' => 10000, 'min_stock' => 2000],
            ['name' => 'Gula Aren', 'unit_id' => $grId, 'stock' => 2000, 'min_stock' => 500],
            ['name' => 'Nasi Putih', 'unit_id' => $grId, 'stock' => 8000, 'min_stock' => 1500],
            ['name' => 'Ayam Fillet', 'unit_id' => $grId, 'stock' => 4000, 'min_stock' => 800],
            ['name' => 'Telur Ayam', 'unit_id' => $pcsId, 'stock' => 100, 'min_stock' => 20],
            ['name' => 'Teh Celup', 'unit_id' => $pcsId, 'stock' => 200, 'min_stock' => 40],
            ['name' => 'Gula Pasir', 'unit_id' => $grId, 'stock' => 5000, 'min_stock' => 1000],
            ['name' => 'Roti Tawar', 'unit_id' => $pcsId, 'stock' => 150, 'min_stock' => 30],
            ['name' => 'Selai Coklat', 'unit_id' => $grId, 'stock' => 3000, 'min_stock' => 500],
            ['name' => 'Margarin', 'unit_id' => $grId, 'stock' => 2000, 'min_stock' => 400],
        ];

        foreach ($materials as $material) {
            \DB::table('raw_materials')->updateOrInsert(
                ['name' => $material['name']],
                [
                    'unit_id' => $material['unit_id'],
                    'stock' => $material['stock'],
                    'min_stock' => $material['min_stock'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
