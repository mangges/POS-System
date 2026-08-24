<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get category IDs
        $foodId = \DB::table('categories')->where('slug', 'food')->value('id');
        $beverageId = \DB::table('categories')->where('slug', 'beverage')->value('id');
        $snackId = \DB::table('categories')->where('slug', 'snack')->value('id');

        $products = [
            [
                'category_id' => $beverageId,
                'name' => 'Kopi Susu Aren',
                'description' => 'Es Kopi Susu dengan Gula Aren',
                'price' => 25000,
                'has_recipe' => true,
                'stock' => null,
                'min_stock' => 0,
                'is_out_of_stock' => false,
                'destination' => 'bar',
            ],
            [
                'category_id' => $foodId,
                'name' => 'Nasi Goreng Spesial',
                'description' => 'Nasi Goreng dengan Telur dan Ayam',
                'price' => 35000,
                'has_recipe' => true,
                'stock' => null,
                'min_stock' => 0,
                'is_out_of_stock' => false,
                'destination' => 'kitchen',
            ],
            [
                'category_id' => $snackId,
                'name' => 'Keripik Kentang',
                'description' => 'Snack Kemasan',
                'price' => 15000,
                'has_recipe' => false,
                'stock' => 50,
                'min_stock' => 10,
                'is_out_of_stock' => false,
                'destination' => 'kitchen',
            ],
            [
                'category_id' => $beverageId,
                'name' => 'Es Teh Manis',
                'description' => 'Teh manis dingin diseduh langsung',
                'price' => 10000,
                'has_recipe' => true,
                'stock' => null,
                'min_stock' => 0,
                'is_out_of_stock' => false,
                'destination' => 'bar',
            ],
            [
                'category_id' => $snackId,
                'name' => 'Roti Bakar Coklat',
                'description' => 'Roti bakar coklat dipanggang fresh',
                'price' => 12000,
                'has_recipe' => true,
                'stock' => null,
                'min_stock' => 0,
                'is_out_of_stock' => false,
                'destination' => 'kitchen',
            ],
        ];

        foreach ($products as $product) {
            \DB::table('products')->updateOrInsert(
                ['name' => $product['name']],
                [...$product, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
