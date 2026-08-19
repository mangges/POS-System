<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Models\Category;
use App\Models\RawMaterial;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateProductPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggling_has_recipe_auto_shows_one_empty_recipe_row(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateProduct::class)
            ->set('data.has_recipe', true)
            ->assertCount('data.recipes', 1);
    }

    public function test_toggling_has_recipe_reveals_recipe_repeater_and_saves_items(): void
    {
        $this->actingAs(User::factory()->create());

        $category = Category::create(['name' => 'Drinks', 'slug' => 'drinks']);
        $unit = Unit::create(['name' => 'Milliliter', 'symbol' => 'ml']);
        $material = RawMaterial::create(['name' => 'Milk', 'unit_id' => $unit->id, 'stock' => 1000]);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'category_id' => $category->id,
                'name' => 'Latte',
                'price' => 25000,
                'has_recipe' => true,
                'destination' => 'bar',
                'is_out_of_stock' => false,
                'is_active' => true,
                'recipes' => [
                    [
                        'raw_material_id' => $material->id,
                        'unit_id' => $unit->id,
                        'quantity' => 150,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('recipes', [
            'raw_material_id' => $material->id,
            'unit_id' => $unit->id,
            'quantity' => 150,
        ]);
    }
}
