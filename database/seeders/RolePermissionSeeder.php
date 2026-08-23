<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'cashier']);

        foreach ([
            'access-categories',
            'access-products',
            'access-raw-materials',
            'access-recipes',
            'access-stock-movements',
            'access-units',
            'access-admin-only',
        ] as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }
}
