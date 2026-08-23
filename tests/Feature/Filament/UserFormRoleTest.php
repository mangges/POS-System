<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserFormRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_a_user_loads_and_saves_their_role(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'cashier']);

        $actor = User::factory()->create();
        $actor->assignRole('admin');
        $this->actingAs($actor);

        $target = User::factory()->create();
        $target->assignRole('cashier');

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertFormSet(['role' => 'cashier'])
            ->fillForm(['role' => 'admin'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($target->fresh()->hasRole('admin'));
        $this->assertFalse($target->fresh()->hasRole('cashier'));
    }

    public function test_create_form_defaults_role_to_cashier_and_assigns_it(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'cashier']);

        $actor = User::factory()->create();
        $actor->assignRole('admin');
        $this->actingAs($actor);

        Livewire::test(CreateUser::class)
            ->assertFormSet(['role' => 'cashier'])
            ->fillForm([
                'name' => 'New Cashier',
                'email' => 'new-cashier@pos.com',
                'password' => 'password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'new-cashier@pos.com')->firstOrFail();
        $this->assertTrue($created->hasRole('cashier'));
    }
}
