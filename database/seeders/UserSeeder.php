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
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@pos.com',
            'password' => \Hash::make('password'),
            'pin' => '123456',
        ]);
        $admin->assignRole('admin');

        $cashier = User::create([
            'name' => 'Cashier 1',
            'email' => 'cashier@pos.com',
            'password' => \Hash::make('password'),
            'pin' => '654321',
        ]);
        $cashier->assignRole('cashier');
    }
}
