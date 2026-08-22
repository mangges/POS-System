<?php

namespace Tests\Feature\Filament;

use App\Enum\Shifts\ShiftStatus;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_reachable(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/shifts')->assertSuccessful();
    }

    public function test_view_page_is_reachable(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $shift = Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 100000,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);

        $this->get("/admin/shifts/{$shift->id}")->assertSuccessful();
    }

    public function test_create_route_does_not_exist(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/shifts/create')->assertNotFound();
    }

    public function test_edit_route_does_not_exist(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $shift = Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 100000,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);

        $this->get("/admin/shifts/{$shift->id}/edit")->assertNotFound();
    }
}
