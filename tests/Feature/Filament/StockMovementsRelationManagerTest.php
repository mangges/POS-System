<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\RawMaterials\Pages\EditRawMaterial;
use App\Filament\Resources\RawMaterials\RelationManagers\StockMovementsRelationManager;
use App\Models\Category;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockMovementsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_a_movement_from_raw_material_increments_stock(): void
    {
        $this->actingAs(User::factory()->create());

        $unit = Unit::create(['name' => 'Gram', 'symbol' => 'g']);
        $material = RawMaterial::create(['name' => 'Sugar', 'unit_id' => $unit->id, 'stock' => 10]);

        Livewire::test(StockMovementsRelationManager::class, [
            'ownerRecord' => $material,
            'pageClass' => EditRawMaterial::class,
        ])
            ->callTableAction('create', data: [
                'type' => 'in',
                'quantity' => 5,
                'notes' => 'Restock from supplier',
            ]);

        $this->assertEquals('15.00', $material->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => 'raw_material',
            'reference_id' => $material->id,
            'type' => 'in',
        ]);
    }

    public function test_relation_manager_hidden_for_product_with_recipe(): void
    {
        $category = Category::create(['name' => 'Drinks', 'slug' => 'drinks']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Latte',
            'price' => 25000,
            'has_recipe' => true,
        ]);

        $this->assertFalse(
            StockMovementsRelationManager::canViewForRecord($product, EditProduct::class)
        );
    }

    public function test_relation_manager_visible_for_product_without_recipe(): void
    {
        $category = Category::create(['name' => 'Snacks', 'slug' => 'snacks']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Bottled Water',
            'price' => 5000,
            'has_recipe' => false,
            'stock' => 20,
        ]);

        $this->assertTrue(
            StockMovementsRelationManager::canViewForRecord($product, EditProduct::class)
        );
    }
}
