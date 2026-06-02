<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tables = [];
        for ($i = 1; $i <= 10; $i++) {
            $tables[] = [
                'number' => 'Meja ' . $i,
                'barcode' => 'TBL-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        \DB::table('tables')->insert($tables);
    }
}
