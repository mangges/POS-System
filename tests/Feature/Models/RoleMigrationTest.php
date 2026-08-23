<?php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_admin_user_gets_admin_spatie_role(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        (require database_path('migrations/2026_08_23_234222_assign_roles_to_existing_users.php'))->up();

        $this->assertTrue($user->fresh()->hasRole('admin'));
    }

    public function test_existing_cashier_user_gets_cashier_spatie_role(): void
    {
        $user = User::factory()->create(['role' => 'cashier']);

        (require database_path('migrations/2026_08_23_234222_assign_roles_to_existing_users.php'))->up();

        $this->assertTrue($user->fresh()->hasRole('cashier'));
    }
}
