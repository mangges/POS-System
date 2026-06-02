<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Food', 'slug' => 'food'],
            ['name' => 'Beverage', 'slug' => 'beverage'],
            ['name' => 'Snack', 'slug' => 'snack'],
        ];

        foreach ($categories as $category) {
            $category['created_at'] = now();
            $category['updated_at'] = now();
            \DB::table('categories')->insert($category);
        }
    }
}
