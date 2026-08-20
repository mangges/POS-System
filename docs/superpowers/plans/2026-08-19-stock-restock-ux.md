# Restock UX + Stock Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restock (stock increase) UX via a Filament relation manager on `RawMaterial`/`Product`, with stock auto-synced from `StockMovement` creation, so any future decrease-on-order-completed trigger reuses the same sync path.

**Architecture:** Register a morph map so `StockMovement::reference()` becomes a real `morphTo()`. A `StockMovementObserver::created()` atomically increments/decrements the reference's `stock` column. A `StockMovementsRelationManager` (attached to both `RawMaterialResource` and `ProductResource`, guarded by `has_recipe` on products) exposes an append-only Create-only table for that history. The standalone `StockMovementResource` loses its Create/Edit pages and becomes a read-only global ledger.

**Tech Stack:** Laravel 12, Filament ^5.6 (relation managers, morph map), PHPUnit (this repo's tests use plain `TestCase` classes, not Pest, despite `pest-plugin` in composer.json — confirmed in prior session).

## Global Constraints

- Movements are append-only: no Edit/Delete actions anywhere on `StockMovement` records once this plan lands.
- `product_id`/`raw_material_id` must never be picked manually in the new UI — always implied by the parent record the relation manager is attached to.
- `stock` changes only ever happen via the `StockMovementObserver`, never by directly setting `->stock = ...` elsewhere.
- Follow existing Filament resource file layout (`Schemas/*Form.php`, `Tables/*Table.php`, `RelationManagers/*.php`) per repo convention.

---

### Task 1: Morph map + real `morphTo()` relation

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Models/StockMovement.php`
- Modify: `app/Models/RawMaterial.php`
- Modify: `app/Models/Product.php`
- Test: `tests/Feature/Models/StockMovementTest.php`

**Interfaces:**
- Produces: `StockMovement::reference(): MorphTo`, `RawMaterial::stockMovements(): MorphMany`, `Product::stockMovements(): MorphMany`. Morph map: `'product' => Product::class`, `'raw_material' => RawMaterial::class` (matches existing string values already stored in `reference_type`, e.g. `RawMaterialSeeder`/`RawMaterial` usage never wrote `reference_type` yet, so no backfill needed — this is a new mechanism, no existing `stock_movements` rows exist in this dev DB).

- [ ] **Step 1: Register morph map**

In `app/Providers/AppServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Models\Product;
use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\Filament\Auth\Http\Responses\Contracts\LogoutResponse::class, function () {
            return new class implements \Filament\Auth\Http\Responses\Contracts\LogoutResponse {
                public function toResponse($request)
                {
                    return redirect()->route('login');
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'product' => Product::class,
            'raw_material' => RawMaterial::class,
        ]);
    }
}
```

- [ ] **Step 2: Replace manual `reference()` with `morphTo()`**

In `app/Models/StockMovement.php`, replace the `reference()` method body:

```php
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
```

(Keep the existing `use Illuminate\Database\Eloquent\Relations\MorphTo;` import — it's already there but unused; it becomes used now.)

- [ ] **Step 3: Add `stockMovements()` to `RawMaterial`**

In `app/Models/RawMaterial.php`, add import `use Illuminate\Database\Eloquent\Relations\MorphMany;` and method:

```php
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
```

- [ ] **Step 4: Add `stockMovements()` to `Product`**

Same as Step 3, in `app/Models/Product.php`.

- [ ] **Step 5: Write failing test**

```php
<?php

namespace Tests\Feature\Models;

use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_resolves_to_raw_material_via_morph_map(): void
    {
        $unit = Unit::create(['name' => 'Gram', 'symbol' => 'g']);
        $material = RawMaterial::create(['name' => 'Sugar', 'unit_id' => $unit->id, 'stock' => 10]);

        $movement = StockMovement::create([
            'reference_id' => $material->id,
            'reference_type' => 'raw_material',
            'type' => 'in',
            'quantity' => 5,
        ]);

        $this->assertTrue($movement->reference->is($material));
        $this->assertTrue($material->stockMovements->contains($movement));
    }
}
```

- [ ] **Step 6: Run test to verify it fails**

Run: `php artisan test --filter=StockMovementTest`
Expected: FAIL (`reference` still resolves via old if/else logic without morph map registered, or class mismatch)

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=StockMovementTest`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Providers/AppServiceProvider.php app/Models/StockMovement.php app/Models/RawMaterial.php app/Models/Product.php tests/Feature/Models/StockMovementTest.php
git commit -m "feat: use real morphTo relation for StockMovement reference"
```

---

### Task 2: `StockMovementObserver` — auto stock sync

**Files:**
- Create: `app/Observers/StockMovementObserver.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Models/StockMovementTest.php` (add cases)

**Interfaces:**
- Consumes: `StockMovement::reference()` (Task 1).
- Produces: side effect only — no new public method. On `StockMovement::create()`, the reference's `stock` column changes.

- [ ] **Step 1: Write the observer**

```php
<?php

namespace App\Observers;

use App\Models\StockMovement;

class StockMovementObserver
{
    public function created(StockMovement $movement): void
    {
        $column = $movement->type === 'in' ? 'increment' : 'decrement';

        $movement->reference()->{$column}('stock', $movement->quantity);
    }
}
```

- [ ] **Step 2: Register the observer**

In `app/Providers/AppServiceProvider.php::boot()`, add above the morph map registration:

```php
use App\Models\StockMovement;
use App\Observers\StockMovementObserver;
```

```php
        StockMovement::observe(StockMovementObserver::class);
```

- [ ] **Step 3: Write failing tests**

Add to `tests/Feature/Models/StockMovementTest.php`:

```php
    public function test_creating_an_in_movement_increments_reference_stock(): void
    {
        $unit = Unit::create(['name' => 'Gram', 'symbol' => 'g']);
        $material = RawMaterial::create(['name' => 'Sugar', 'unit_id' => $unit->id, 'stock' => 10]);

        StockMovement::create([
            'reference_id' => $material->id,
            'reference_type' => 'raw_material',
            'type' => 'in',
            'quantity' => 5,
        ]);

        $this->assertEquals('15.00', $material->fresh()->stock);
    }

    public function test_creating_an_out_movement_decrements_reference_stock(): void
    {
        $unit = Unit::create(['name' => 'Gram', 'symbol' => 'g']);
        $material = RawMaterial::create(['name' => 'Sugar', 'unit_id' => $unit->id, 'stock' => 10]);

        StockMovement::create([
            'reference_id' => $material->id,
            'reference_type' => 'raw_material',
            'type' => 'out',
            'quantity' => 3,
        ]);

        $this->assertEquals('7.00', $material->fresh()->stock);
    }
```

- [ ] **Step 4: Run tests to verify they fail**

Run: `php artisan test --filter=StockMovementTest`
Expected: FAIL (`stock` unchanged, still 10.00, because observer not wired yet)

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=StockMovementTest`
Expected: PASS (all 3 tests in this file)

- [ ] **Step 6: Commit**

```bash
git add app/Observers/StockMovementObserver.php app/Providers/AppServiceProvider.php tests/Feature/Models/StockMovementTest.php
git commit -m "feat: auto-sync reference stock when a StockMovement is created"
```

---

### Task 3: `StockMovementsRelationManager` (shared by RawMaterial + Product)

**Files:**
- Create: `app/Filament/Resources/RawMaterials/RelationManagers/StockMovementsRelationManager.php`
- Modify: `app/Filament/Resources/RawMaterials/RawMaterialResource.php`
- Modify: `app/Filament/Resources/Products/ProductResource.php`
- Test: `tests/Feature/Filament/StockMovementsRelationManagerTest.php`

**Interfaces:**
- Consumes: `RawMaterial::stockMovements()`, `Product::stockMovements()` (Task 1), `StockMovementObserver` (Task 2, implicitly — stock updates when a movement is added through this UI).
- Produces: a relation manager class used by both resources (same class, registered on both — Filament relation managers aren't resource-specific by class, only by the `getRelations()` array entry).

- [ ] **Step 1: Scaffold via artisan**

Run: `php artisan make:filament-relation-manager RawMaterials.RawMaterialResource stockMovements type --no-interaction`

This creates `app/Filament/Resources/RawMaterials/RelationManagers/StockMovementsRelationManager.php` with `type` as the title/searchable attribute placeholder — it will be rewritten in the next step regardless.

- [ ] **Step 2: Write the relation manager**

```php
<?php

namespace App\Filament\Resources\RawMaterials\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class StockMovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockMovements';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! ($ownerRecord instanceof \App\Models\Product)) {
            return true;
        }

        return ! $ownerRecord->has_recipe;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options(['in' => 'In', 'out' => 'Out'])
                    ->default('in')
                    ->required(),
                TextInput::make('quantity')
                    ->required()
                    ->numeric(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'in' ? 'success' : 'danger'),
                TextColumn::make('quantity')
                    ->numeric(),
                TextColumn::make('notes')
                    ->limit(50),
                TextColumn::make('user.name')
                    ->label('By'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Movement')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
```

- [ ] **Step 3: Register on `RawMaterialResource`**

In `app/Filament/Resources/RawMaterials/RawMaterialResource.php`, add import
`use App\Filament\Resources\RawMaterials\RelationManagers\StockMovementsRelationManager;`
and change `getRelations()`:

```php
    public static function getRelations(): array
    {
        return [
            StockMovementsRelationManager::class,
        ];
    }
```

- [ ] **Step 4: Register on `ProductResource`**

In `app/Filament/Resources/Products/ProductResource.php`, add import
`use App\Filament\Resources\RawMaterials\RelationManagers\StockMovementsRelationManager;`
and change `getRelations()`:

```php
    public static function getRelations(): array
    {
        return [
            StockMovementsRelationManager::class,
        ];
    }
```

- [ ] **Step 5: Write failing test**

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\RawMaterials\RelationManagers\StockMovementsRelationManager;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Category;
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
            'pageClass' => \App\Filament\Resources\RawMaterials\Pages\EditRawMaterial::class,
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
            StockMovementsRelationManager::canViewForRecord($product, \App\Filament\Resources\Products\Pages\EditProduct::class)
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
            StockMovementsRelationManager::canViewForRecord($product, \App\Filament\Resources\Products\Pages\EditProduct::class)
        );
    }
}
```

- [ ] **Step 6: Run tests to verify they fail**

Run: `php artisan test --filter=StockMovementsRelationManagerTest`
Expected: FAIL (relation manager / registration missing)

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=StockMovementsRelationManagerTest`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Resources/RawMaterials/RelationManagers app/Filament/Resources/RawMaterials/RawMaterialResource.php app/Filament/Resources/Products/ProductResource.php tests/Feature/Filament/StockMovementsRelationManagerTest.php
git commit -m "feat: add stock movement history + restock relation manager"
```

---

### Task 4: `StockMovementResource` becomes read-only global ledger

**Files:**
- Modify: `app/Filament/Resources/StockMovements/StockMovementResource.php`
- Modify: `app/Filament/Resources/StockMovements/Tables/StockMovementsTable.php`
- Delete: `app/Filament/Resources/StockMovements/Pages/CreateStockMovement.php`
- Delete: `app/Filament/Resources/StockMovements/Pages/EditStockMovement.php`
- Delete: `app/Filament/Resources/StockMovements/Schemas/StockMovementForm.php`
- Test: `tests/Feature/Filament/StockMovementResourceTest.php`

**Interfaces:**
- Consumes: `StockMovement::reference()` (Task 1) to display a human-readable reference name.

- [ ] **Step 1: Trim `getPages()`**

In `app/Filament/Resources/StockMovements/StockMovementResource.php`:

```php
    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
        ];
    }
```

Remove the now-unused `CreateStockMovement`/`EditStockMovement` imports.

- [ ] **Step 2: Delete the create/edit pages and form schema**

```bash
rm app/Filament/Resources/StockMovements/Pages/CreateStockMovement.php
rm app/Filament/Resources/StockMovements/Pages/EditStockMovement.php
rm app/Filament/Resources/StockMovements/Schemas/StockMovementForm.php
```

- [ ] **Step 3: Update the table — readable reference name, drop edit action**

```php
<?php

namespace App\Filament\Resources\StockMovements\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference.name')
                    ->label('Item')
                    ->searchable(),
                TextColumn::make('reference_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'in' ? 'success' : 'danger'),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([]);
    }
}
```

- [ ] **Step 4: Write failing test**

```php
<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_reachable(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/stock-movements')->assertSuccessful();
    }

    public function test_create_route_no_longer_exists(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/stock-movements/create')->assertNotFound();
    }
}
```

(Adjust the URL prefix in the test to match this app's actual Filament panel path if it isn't `/admin` — check `app/Providers/Filament/AdminPanelProvider.php` `->path()` before running.)

- [ ] **Step 5: Run test to verify it fails**

Run: `php artisan test --filter=StockMovementResourceTest`
Expected: FAIL (create route still exists, returns 200 not 404)

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=StockMovementResourceTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/StockMovements tests/Feature/Filament/StockMovementResourceTest.php
git commit -m "refactor: make StockMovementResource a read-only global ledger"
```

---

### Task 5: Full regression check

**Files:** none (verification only)

- [ ] **Step 1: Fresh migrate + seed + full test suite**

Run: `php artisan migrate:fresh --seed && php artisan test`
Expected: all tests PASS except the pre-existing unrelated `ExampleTest` failure (302 redirect on `/`, confirmed pre-existing in prior session — not caused by this work).

- [ ] **Step 2: Manual smoke check in browser**

Visit `/admin/raw-materials/{id}/edit`, confirm "Stock Movements" tab appears, add an `in` movement, confirm stock number updates on the parent record and the row appears with no edit/delete controls.

Visit a product with `has_recipe = true` — confirm no "Stock Movements" tab. Visit one with `has_recipe = false` — confirm the tab appears and behaves the same way.
