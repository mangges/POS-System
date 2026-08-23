<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_pin_login_redirects_admin_to_admin_panel(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['pin' => '111111']);
        $user->assignRole('admin');

        Livewire::test(Login::class)
            ->set('pin', '111111')
            ->call('loginWithPin')
            ->assertRedirect('/admin');
    }

    public function test_pin_login_redirects_cashier_to_cashier_view(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        $user = User::factory()->create(['pin' => '222222']);
        $user->assignRole('cashier');

        Livewire::test(Login::class)
            ->set('pin', '222222')
            ->call('loginWithPin')
            ->assertRedirect('/cashier');
    }

    public function test_login_clears_stale_permissions_from_previous_session(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-products']);
        $user = User::factory()->create(['pin' => '333333']);
        $user->assignRole('cashier');
        $user->syncPermissions(['access-products']);

        Livewire::test(Login::class)
            ->set('pin', '333333')
            ->call('loginWithPin');

        $this->assertCount(0, $user->fresh()->getDirectPermissions());
    }
}
