<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EditProductPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_recipe_product_with_no_recipes_shows_one_empty_row_and_saves_it(): void
    {
        $this->actingAs(User::factory()->create());

        $category = Category::create(['name' => 'Drinks', 'slug' => 'drinks']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Kopi Susu Aren',
            'price' => 25000,
            'has_recipe' => true,
        ]);
        $unit = Unit::create(['name' => 'Milliliter', 'symbol' => 'ml']);
        $material = RawMaterial::create(['name' => 'Susu Cair', 'unit_id' => $unit->id, 'stock' => 1000]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertCount('data.recipes', 1)
            ->fillForm([
                'recipes' => [
                    [
                        'raw_material_id' => $material->id,
                        'unit_id' => $unit->id,
                        'quantity' => 150,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('recipes', [
            'product_id' => $product->id,
            'raw_material_id' => $material->id,
            'quantity' => '150.00',
        ]);
    }

    public function test_non_recipe_product_does_not_require_recipe_rows(): void
    {
        $this->actingAs(User::factory()->create());

        $category = Category::create(['name' => 'Snacks', 'slug' => 'snacks']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Chips',
            'price' => 10000,
            'has_recipe' => false,
            'stock' => 20,
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();
    }
}
