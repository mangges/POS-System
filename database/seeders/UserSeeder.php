<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \DB::table('users')->insert([
            [
                'name' => 'Administrator',
                'email' => 'admin@pos.com',
                'password' => \Hash::make('password'),
                'pin' => '123456',
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Cashier 1',
                'email' => 'cashier@pos.com',
                'password' => \Hash::make('password'),
                'pin' => '654321',
                'role' => 'cashier',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
