<?php

namespace Tests\Feature\Livewire\Pos;

use App\Enum\Shifts\ShiftStatus;
use App\Livewire\Pos\Cashier;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashierShiftTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(string $name, float $price): Product
    {
        $category = Category::firstOrCreate(['slug' => 'food'], ['name' => 'Food']);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'price' => $price,
            'has_recipe' => false,
            'stock' => 10,
            'is_out_of_stock' => false,
            'destination' => 'kitchen',
            'is_active' => true,
        ]);
    }

    public function test_renders_blocking_screen_when_no_active_shift(): void
    {
        $this->actingAs(User::factory()->create());
        $this->createProduct('Nasi Goreng', 20000);

        Livewire::test(Cashier::class)
            ->assertSee('Buka Shift')
            ->assertDontSee('Nasi Goreng');
    }

    public function test_renders_pos_normally_when_shift_active(): void
    {
        $user = User::factory()->create();
        Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 100000,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);
        $this->actingAs($user);
        $this->createProduct('Nasi Goreng', 20000);

        Livewire::test(Cashier::class)
            ->assertSee('Nasi Goreng')
            ->assertDontSee('Buka Shift');
    }

    public function test_opening_a_shift_creates_it_and_reveals_the_pos(): void
    {
        $this->actingAs(User::factory()->create());
        $this->createProduct('Nasi Goreng', 20000);

        Livewire::test(Cashier::class)
            ->set('shiftOpeningCash', '150000')
            ->call('openShift')
            ->assertSee('Nasi Goreng')
            ->assertDontSee('Buka Shift');

        $this->assertSame(1, Shift::count());
        $this->assertSame(150000.0, (float) Shift::first()->opening_cash);
    }

    public function test_opening_a_shift_fails_when_one_is_already_open(): void
    {
        $user = User::factory()->create();
        Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 100000,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);
        $this->actingAs($user);

        Livewire::test(Cashier::class)
            ->set('shiftOpeningCash', '50000')
            ->call('openShift')
            ->assertHasErrors('shiftOpeningCash');

        $this->assertSame(1, Shift::count());
    }
}
