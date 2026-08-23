<?php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_assigned_a_spatie_role(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        $user = User::factory()->create();

        $user->assignRole('cashier');

        $this->assertTrue($user->hasRole('cashier'));
    }
}
