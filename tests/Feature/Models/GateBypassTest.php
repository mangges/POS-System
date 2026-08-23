<?php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GateBypassTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_passes_can_check_for_permission_never_assigned(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue($admin->can('some-permission-that-was-never-created'));
    }

    public function test_cashier_does_not_bypass_permission_checks(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $this->assertFalse($cashier->can('access-admin-only'));
    }
}
