<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RecipeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $productId = fn (string $name) => \DB::table('products')->where('name', $name)->value('id');
        $materialId = fn (string $name) => \DB::table('raw_materials')->where('name', $name)->value('id');
        $unitId = fn (string $symbol) => \DB::table('units')->where('symbol', $symbol)->value('id');

        $recipes = [
            ['product' => 'Kopi Susu Aren', 'material' => 'Biji Kopi Arabica', 'unit' => 'gr', 'quantity' => 18],
            ['product' => 'Kopi Susu Aren', 'material' => 'Susu Cair', 'unit' => 'ml', 'quantity' => 150],
            ['product' => 'Kopi Susu Aren', 'material' => 'Gula Aren', 'unit' => 'gr', 'quantity' => 20],
            ['product' => 'Nasi Goreng Spesial', 'material' => 'Nasi Putih', 'unit' => 'gr', 'quantity' => 200],
            ['product' => 'Nasi Goreng Spesial', 'material' => 'Ayam Fillet', 'unit' => 'gr', 'quantity' => 80],
            ['product' => 'Nasi Goreng Spesial', 'material' => 'Telur Ayam', 'unit' => 'pcs', 'quantity' => 1],
            ['product' => 'Es Teh Manis', 'material' => 'Teh Celup', 'unit' => 'pcs', 'quantity' => 1],
            ['product' => 'Es Teh Manis', 'material' => 'Gula Pasir', 'unit' => 'gr', 'quantity' => 25],
            ['product' => 'Roti Bakar Coklat', 'material' => 'Roti Tawar', 'unit' => 'pcs', 'quantity' => 2],
            ['product' => 'Roti Bakar Coklat', 'material' => 'Selai Coklat', 'unit' => 'gr', 'quantity' => 30],
            ['product' => 'Roti Bakar Coklat', 'material' => 'Margarin', 'unit' => 'gr', 'quantity' => 10],
        ];

        foreach ($recipes as $recipe) {
            \DB::table('recipes')->updateOrInsert(
                [
                    'product_id' => $productId($recipe['product']),
                    'raw_material_id' => $materialId($recipe['material']),
                ],
                [
                    'unit_id' => $unitId($recipe['unit']),
                    'quantity' => $recipe['quantity'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
