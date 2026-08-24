<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnsureResourcePinUnlockedTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_without_permission_is_redirected_to_pin_gate(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-categories']);
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $this->actingAs($user);

        $response = $this->get('/admin/categories');

        $response->assertRedirect();
        $this->assertStringContainsString('resource=categories', $response->headers->get('Location'));
    }

    public function test_cashier_with_permission_passes_through(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-categories']);
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $user->syncPermissions(['access-categories']);
        $this->actingAs($user);

        $response = $this->get('/admin/categories');

        $response->assertOk();
    }

    public function test_cashier_with_active_session_unlock_passes_through(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-categories']);
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $this->actingAs($user);
        session()->put('pin_unlocked.categories', true);

        $response = $this->get('/admin/categories');

        $response->assertOk();
    }

    public function test_revisiting_after_leaving_the_resource_asks_for_the_pin_again(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-categories']);
        Permission::firstOrCreate(['name' => 'access-products']);
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $this->actingAs($user);
        session()->put('pin_unlocked.categories', true);

        // Cashier navigates to an unrelated resource, leaving categories behind.
        $this->get('/admin/products');

        $response = $this->get('/admin/categories');

        $response->assertRedirect();
        $this->assertStringContainsString('resource=categories', $response->headers->get('Location'));
    }

    public function test_admin_passes_through_without_permission(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $response = $this->get('/admin/categories');

        $response->assertOk();
    }
}
