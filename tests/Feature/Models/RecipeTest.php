<?php

namespace Tests\Feature\Models;

use App\Models\Category;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Recipe;
use App\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): Product
    {
        $category = Category::create(['name' => 'Drinks', 'slug' => 'drinks']);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Latte',
            'price' => 25000,
            'has_recipe' => true,
        ]);
    }

    public function test_links_a_product_to_a_raw_material_with_a_quantity(): void
    {
        $product = $this->makeProduct();
        $unit = Unit::create(['name' => 'Milliliter', 'symbol' => 'ml']);
        $material = RawMaterial::create(['name' => 'Milk', 'unit_id' => $unit->id, 'stock' => 1000]);

        $recipe = Recipe::create([
            'product_id' => $product->id,
            'raw_material_id' => $material->id,
            'unit_id' => $unit->id,
            'quantity' => 150,
        ]);

        $this->assertCount(1, $product->recipes);
        $this->assertSame('Milk', $recipe->rawMaterial->name);
        $this->assertEquals('150.00', $recipe->quantity);
    }

    public function test_rejects_a_duplicate_product_raw_material_pair(): void
    {
        $product = $this->makeProduct();
        $unit = Unit::create(['name' => 'Milliliter', 'symbol' => 'ml']);
        $material = RawMaterial::create(['name' => 'Milk', 'unit_id' => $unit->id, 'stock' => 1000]);

        Recipe::create(['product_id' => $product->id, 'raw_material_id' => $material->id, 'unit_id' => $unit->id, 'quantity' => 150]);

        $this->expectException(QueryException::class);

        Recipe::create(['product_id' => $product->id, 'raw_material_id' => $material->id, 'unit_id' => $unit->id, 'quantity' => 200]);
    }
}
