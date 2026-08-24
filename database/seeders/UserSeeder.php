<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@pos.com'],
            [
                'name' => 'Administrator',
                'password' => \Hash::make('password'),
                'pin' => '123456',
            ]
        );
        $admin->assignRole('admin');

        $cashier = User::firstOrCreate(
            ['email' => 'cashier@pos.com'],
            [
                'name' => 'Cashier 1',
                'password' => \Hash::make('password'),
                'pin' => '654321',
            ]
        );
        $cashier->assignRole('cashier');
    }
}
