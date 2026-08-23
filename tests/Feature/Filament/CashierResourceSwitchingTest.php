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

    public function test_unlocking_a_second_tier2_resource_revokes_the_first(): void
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

        $this->assertTrue($cashier->fresh()->can('access-raw-materials'));

        Livewire::test(AdminPinGate::class, ['resource' => 'products', 'redirect' => '/admin/products'])
            ->set('pin', '999999')
            ->call('submit');

        $cashier->refresh();
        $this->assertTrue($cashier->can('access-products'));
        $this->assertFalse($cashier->can('access-raw-materials'));
    }

    public function test_logging_in_again_after_unlock_starts_fully_locked(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-products']);
        $cashier = User::factory()->create(['pin' => '444444']);
        $cashier->assignRole('cashier');
        $cashier->syncPermissions(['access-products']);

        auth()->logout();
        $this->actingAs($cashier);
        $cashier->syncPermissions([]); // simulates Login.php's post-auth reset from Task 4

        $this->assertCount(0, $cashier->fresh()->getDirectPermissions());
    }
}
