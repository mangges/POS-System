<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockMovementResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_reachable(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->get('/admin/stock-movements')->assertSuccessful();
    }

    public function test_create_route_no_longer_exists(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/stock-movements/create')->assertNotFound();
    }
}
