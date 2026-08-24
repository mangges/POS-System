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
            \DB::table('categories')->updateOrInsert(
                ['slug' => $category['slug']],
                ['name' => $category['name'], 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
