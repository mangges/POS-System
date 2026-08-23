<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\AdminPinGate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPinGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_wrong_pin_shows_error_and_changes_no_permission(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-categories']);
        User::factory()->create(['pin' => '999999'])->assignRole('admin');
        $cashier = User::factory()->create(['pin' => '222222']);
        $cashier->assignRole('cashier');
        $this->actingAs($cashier);

        Livewire::test(AdminPinGate::class, ['resource' => 'categories', 'redirect' => '/admin/categories'])
            ->set('pin', '000000')
            ->call('submit');

        $this->assertCount(0, $cashier->fresh()->getDirectPermissions());
    }

    public function test_correct_admin_pin_grants_permission_and_redirects(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-categories']);
        User::factory()->create(['pin' => '999999'])->assignRole('admin');
        $cashier = User::factory()->create(['pin' => '222222']);
        $cashier->assignRole('cashier');
        $this->actingAs($cashier);

        Livewire::test(AdminPinGate::class, ['resource' => 'categories', 'redirect' => '/admin/categories'])
            ->set('pin', '999999')
            ->call('submit')
            ->assertRedirect('/admin/categories');

        $this->assertTrue($cashier->fresh()->can('access-categories'));
    }

    public function test_tier_3_resource_cannot_be_unlocked_via_this_page_even_with_correct_pin(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-admin-only']);
        User::factory()->create(['pin' => '999999'])->assignRole('admin');
        $cashier = User::factory()->create(['pin' => '222222']);
        $cashier->assignRole('cashier');
        $this->actingAs($cashier);

        Livewire::test(AdminPinGate::class, ['resource' => 'admin-only', 'redirect' => '/admin/users'])
            ->set('pin', '999999')
            ->call('submit');

        $this->assertFalse($cashier->fresh()->can('access-admin-only'));
        $this->assertCount(0, $cashier->fresh()->getDirectPermissions());
    }

    public function test_external_redirect_url_is_rejected(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-categories']);
        User::factory()->create(['pin' => '999999'])->assignRole('admin');
        $cashier = User::factory()->create(['pin' => '222222']);
        $cashier->assignRole('cashier');
        $this->actingAs($cashier);

        Livewire::test(AdminPinGate::class, ['resource' => 'categories', 'redirect' => 'https://evil.example.com'])
            ->set('pin', '999999')
            ->call('submit')
            ->assertRedirect('/admin');

        Livewire::test(AdminPinGate::class, ['resource' => 'categories', 'redirect' => '//evil.example.com'])
            ->set('pin', '999999')
            ->call('submit')
            ->assertRedirect('/admin');
    }
}
