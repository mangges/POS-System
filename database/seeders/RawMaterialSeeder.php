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
        $materials = [
            ['name' => 'Biji Kopi Arabica', 'unit' => 'gr', 'stock' => 5000],
            ['name' => 'Susu Cair', 'unit' => 'ml', 'stock' => 10000],
            ['name' => 'Gula Aren', 'unit' => 'ml', 'stock' => 2000],
        ];

        foreach ($materials as $material) {
            $material['created_at'] = now();
            $material['updated_at'] = now();
            \DB::table('raw_materials')->insert($material);
        }
    }
}
