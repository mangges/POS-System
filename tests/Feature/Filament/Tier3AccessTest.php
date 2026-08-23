<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\PaymentMethodSettings;
use App\Filament\Pages\ReceiptSettings;
use App\Filament\Pages\Reports\ProductReport;
use App\Filament\Pages\Reports\SalesReport;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Tier3AccessTest extends TestCase
{
    use RefreshDatabase;

    public static function tier3Classes(): array
    {
        return [
            [UserResource::class],
            [SalesReport::class],
            [ProductReport::class],
            [PaymentMethodSettings::class],
            [ReceiptSettings::class],
        ];
    }

    #[DataProvider('tier3Classes')]
    public function test_cashier_cannot_access_tier3(string $class): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-admin-only']);
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $this->actingAs($user);

        $this->assertFalse($class::canAccess());
    }

    #[DataProvider('tier3Classes')]
    public function test_admin_can_access_tier3(string $class): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->assertTrue($class::canAccess());
    }

    public function test_cashier_with_no_permissions_still_accesses_tier1_resource(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $this->actingAs($user);

        $this->assertTrue(\App\Filament\Resources\Orders\OrderResource::canAccess());
    }
}
