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
        ];

        foreach ($units as $unit) {
            $unit['created_at'] = now();
            $unit['updated_at'] = now();
            \DB::table('units')->insert($unit);
        }

        $grId = \DB::table('units')->where('symbol', 'gr')->value('id');
        $mlId = \DB::table('units')->where('symbol', 'ml')->value('id');

        $materials = [
            ['name' => 'Biji Kopi Arabica', 'unit_id' => $grId, 'stock' => 5000],
            ['name' => 'Susu Cair', 'unit_id' => $mlId, 'stock' => 10000],
            ['name' => 'Gula Aren', 'unit_id' => $mlId, 'stock' => 2000],
        ];

        foreach ($materials as $material) {
            $material['created_at'] = now();
            $material['updated_at'] = now();
            \DB::table('raw_materials')->insert($material);
        }
    }
}
