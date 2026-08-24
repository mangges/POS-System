<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\AdminPinGate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashierResourceSwitchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unlocking_a_second_tier2_resource_does_not_touch_the_first(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-raw-materials']);
        Permission::firstOrCreate(['name' => 'access-products']);
        User::factory()->create(['pin' => '999999'])->assignRole('admin');
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $this->actingAs($cashier);

        Livewire::test(AdminPinGate::class, ['resource' => 'raw-materials', 'redirect' => '/admin/raw-materials'])
            ->set('pin', '999999')
            ->call('submit');

        $this->assertTrue(session()->has('pin_unlocked.raw-materials'));

        Livewire::test(AdminPinGate::class, ['resource' => 'products', 'redirect' => '/admin/products'])
            ->set('pin', '999999')
            ->call('submit');

        $this->assertTrue(session()->has('pin_unlocked.products'));
        $this->assertTrue(session()->has('pin_unlocked.raw-materials'));
    }

    public function test_navigating_away_from_the_resource_revokes_its_unlock(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-raw-materials']);
        Permission::firstOrCreate(['name' => 'access-products']);
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $this->actingAs($cashier);
        session()->put('pin_unlocked.raw-materials', true);

        $this->get('/admin/raw-materials')->assertOk();

        // Visiting an unrelated protected resource means the cashier left
        // raw-materials behind, so it must ask for the PIN again next time.
        $this->get('/admin/products');

        $this->assertFalse(session()->has('pin_unlocked.raw-materials'));
    }

    public function test_logging_in_again_after_unlock_starts_fully_locked(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-products']);
        $cashier = User::factory()->create(['pin' => '444444']);
        $cashier->assignRole('cashier');
        $cashier->syncPermissions(['access-products']);
        session()->put('pin_unlocked.raw-materials', true);

        auth()->logout();

        Livewire::test(\App\Livewire\Auth\Login::class)
            ->set('pin', '444444')
            ->call('loginWithPin');

        $this->assertCount(0, $cashier->fresh()->getDirectPermissions());
        $this->assertFalse(session()->has('pin_unlocked.raw-materials'));
    }
}
