<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'cashier']);

        foreach (User::all() as $user) {
            if (! $user->hasRole($user->role)) {
                $user->assignRole($user->role);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
