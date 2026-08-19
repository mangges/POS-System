# Unit & Recipe Inventory Models Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `Unit` and `Recipe` models so raw-material units are standardized and products with `has_recipe = true` can define their bahan baku composition, plus an index on `stock_movements` for reporting.

**Architecture:** Standard Laravel migration + Eloquent model + Filament resource, following the exact pattern already used by `RawMaterialResource` / `StockMovementResource` (`app/Filament/Resources/<Name>/{<Name>Resource.php,Schemas/<Name>Form.php,Tables/<Name>sTable.php,Pages/*}`).

**Tech Stack:** Laravel 12, Filament 4, SQLite/MySQL (whatever `.env` points at), Pest for tests.

## Global Constraints

- Follow existing Filament resource file layout exactly (Resource class + `Schemas/*Form.php` + `Tables/*Table.php` + `Pages/{List,Create,Edit}*.php`), per spec.
- Nav group "Inventory": Unit sort 0, RawMaterial sort 1 (existing), StockMovement sort 2 (existing), Recipe sort 3.
- `products.stock` stays authoritative only when `has_recipe = false`; no code changes to Product stock logic in this plan (out of scope per spec).
- No auto-deduct stock logic — out of scope per spec.

---

### Task 1: `units` table + `Unit` model

**Files:**
- Create: `database/migrations/2026_08_19_000001_create_units_table.php`
- Create: `app/Models/Unit.php`
- Test: `tests/Feature/Models/UnitTest.php`

**Interfaces:**
- Produces: `Unit` model with fillable `name`, `symbol`; table `units(id, name, symbol unique, timestamps)`.

- [ ] **Step 1: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('symbol')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
```

- [ ] **Step 2: Write model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'symbol'];

    public function rawMaterials(): HasMany
    {
        return $this->hasMany(RawMaterial::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }
}
```

- [ ] **Step 3: Write failing test**

```php
<?php

use App\Models\Unit;

it('creates a unit', function () {
    $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg']);

    expect($unit->fresh())
        ->name->toBe('Kilogram')
        ->symbol->toBe('kg');
});

it('enforces unique symbol', function () {
    Unit::create(['name' => 'Kilogram', 'symbol' => 'kg']);

    Unit::create(['name' => 'Kilo', 'symbol' => 'kg']);
})->throws(\Illuminate\Database\QueryException::class);
```

- [ ] **Step 4: Run test to verify it fails**

Run: `php artisan test --filter=UnitTest`
Expected: FAIL (`units` table / `Unit` class not migrated yet if run before migrate, or class not found)

- [ ] **Step 5: Migrate and run test to verify it passes**

Run: `php artisan migrate && php artisan test --filter=UnitTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_19_000001_create_units_table.php app/Models/Unit.php tests/Feature/Models/UnitTest.php
git commit -m "feat: add Unit model for standardized measurement units"
```

---

### Task 2: `raw_materials.unit` string → `unit_id` FK

**Files:**
- Create: `database/migrations/2026_08_19_000002_add_unit_id_to_raw_materials_table.php`
- Modify: `app/Models/RawMaterial.php`
- Test: `tests/Feature/Models/RawMaterialTest.php`

**Interfaces:**
- Consumes: `Unit` model (Task 1).
- Produces: `RawMaterial::unit()` (`belongsTo(Unit::class)`), `RawMaterial::recipes()` (`hasMany(Recipe::class)`, added now so Task 3 doesn't need to touch this file again). `raw_materials.unit_id` FK, string column `unit` dropped.

- [ ] **Step 1: Write migration (add, backfill, drop)**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('unit')->constrained()->nullOnDelete();
        });

        DB::table('raw_materials')->select('unit')->distinct()->pluck('unit')->each(function (string $symbol) {
            $unitId = DB::table('units')->where('symbol', $symbol)->value('id');

            if (! $unitId) {
                $unitId = DB::table('units')->insertGetId([
                    'name' => $symbol,
                    'symbol' => $symbol,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('raw_materials')->where('unit', $symbol)->update(['unit_id' => $unitId]);
        });

        Schema::table('raw_materials', function (Blueprint $table) {
            $table->dropColumn('unit');
            $table->foreignId('unit_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->string('unit')->default('')->after('name');
        });

        DB::table('raw_materials')->get()->each(function ($row) {
            $symbol = DB::table('units')->where('id', $row->unit_id)->value('symbol') ?? '';
            DB::table('raw_materials')->where('id', $row->id)->update(['unit' => $symbol]);
        });

        Schema::table('raw_materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
        });
    }
};
```

- [ ] **Step 2: Update model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RawMaterial extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'unit_id', 'stock'];

    protected $casts = [
        'stock' => 'decimal:2',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }
}
```

- [ ] **Step 3: Write failing test**

```php
<?php

use App\Models\RawMaterial;
use App\Models\Unit;

it('belongs to a unit', function () {
    $unit = Unit::create(['name' => 'Gram', 'symbol' => 'g']);
    $material = RawMaterial::create(['name' => 'Sugar', 'unit_id' => $unit->id, 'stock' => 10]);

    expect($material->unit)->toBeInstanceOf(Unit::class)
        ->and($material->unit->symbol)->toBe('g');
});
```

- [ ] **Step 4: Run test to verify it fails**

Run: `php artisan test --filter=RawMaterialTest`
Expected: FAIL (column `unit_id` doesn't exist yet / relation missing)

- [ ] **Step 5: Migrate and verify backfill on empty DB, run test**

Run: `php artisan migrate:fresh && php artisan test --filter=RawMaterialTest`
Expected: migration runs clean on empty DB (no rows to backfill, since this is a dev DB with no raw_materials data yet), test PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_19_000002_add_unit_id_to_raw_materials_table.php app/Models/RawMaterial.php tests/Feature/Models/RawMaterialTest.php
git commit -m "feat: replace raw_materials.unit string with unit_id FK"
```

---

### Task 3: `recipes` table + `Recipe` model + `Product::recipes()`

**Files:**
- Create: `database/migrations/2026_08_19_000003_create_recipes_table.php`
- Create: `app/Models/Recipe.php`
- Modify: `app/Models/Product.php`
- Test: `tests/Feature/Models/RecipeTest.php`

**Interfaces:**
- Consumes: `Unit` (Task 1), `RawMaterial` (Task 2), `Product` (existing).
- Produces: `Recipe` model (`belongsTo` Product/RawMaterial/Unit, fillable `product_id, raw_material_id, unit_id, quantity`), `Product::recipes()` (`hasMany(Recipe::class)`).

- [ ] **Step 1: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained();
            $table->decimal('quantity', 10, 2);
            $table->timestamps();

            $table->unique(['product_id', 'raw_material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
```

- [ ] **Step 2: Write model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'raw_material_id', 'unit_id', 'quantity'];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
```

- [ ] **Step 3: Add relation to `Product`**

In `app/Models/Product.php`, add import `use Illuminate\Database\Eloquent\Relations\HasMany;` and method:

```php
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }
```

- [ ] **Step 4: Write failing test**

```php
<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Recipe;
use App\Models\Unit;

it('links a product to a raw material with a quantity', function () {
    $category = Category::create(['name' => 'Drinks']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Latte',
        'price' => 25000,
        'has_recipe' => true,
    ]);
    $unit = Unit::create(['name' => 'Milliliter', 'symbol' => 'ml']);
    $material = RawMaterial::create(['name' => 'Milk', 'unit_id' => $unit->id, 'stock' => 1000]);

    $recipe = Recipe::create([
        'product_id' => $product->id,
        'raw_material_id' => $material->id,
        'unit_id' => $unit->id,
        'quantity' => 150,
    ]);

    expect($product->recipes)->toHaveCount(1)
        ->and($recipe->rawMaterial->name)->toBe('Milk')
        ->and($recipe->quantity)->toEqual('150.00');
});

it('rejects a duplicate product/raw_material pair', function () {
    $category = Category::create(['name' => 'Drinks']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Latte',
        'price' => 25000,
        'has_recipe' => true,
    ]);
    $unit = Unit::create(['name' => 'Milliliter', 'symbol' => 'ml']);
    $material = RawMaterial::create(['name' => 'Milk', 'unit_id' => $unit->id, 'stock' => 1000]);

    Recipe::create(['product_id' => $product->id, 'raw_material_id' => $material->id, 'unit_id' => $unit->id, 'quantity' => 150]);
    Recipe::create(['product_id' => $product->id, 'raw_material_id' => $material->id, 'unit_id' => $unit->id, 'quantity' => 200]);
})->throws(\Illuminate\Database\QueryException::class);
```

- [ ] **Step 5: Run test to verify it fails**

Run: `php artisan test --filter=RecipeTest`
Expected: FAIL (table/class not present)

- [ ] **Step 6: Migrate and run test to verify it passes**

Run: `php artisan migrate && php artisan test --filter=RecipeTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_19_000003_create_recipes_table.php app/Models/Recipe.php app/Models/Product.php tests/Feature/Models/RecipeTest.php
git commit -m "feat: add Recipe model linking products to raw materials"
```

---

### Task 4: `stock_movements` reporting index

**Files:**
- Create: `database/migrations/2026_08_19_000004_add_reference_index_to_stock_movements_table.php`

**Interfaces:**
- Consumes: existing `stock_movements` table (`reference_type`, `reference_id` columns).
- Produces: composite index `stock_movements_reference_type_reference_id_index`.

- [ ] **Step 1: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['reference_type', 'reference_id']);
        });
    }
};
```

- [ ] **Step 2: Migrate**

Run: `php artisan migrate`
Expected: migrates clean, no output errors

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_08_19_000004_add_reference_index_to_stock_movements_table.php
git commit -m "perf: index stock_movements reference columns for reporting"
```

---

### Task 5: `UnitResource` (Filament)

**Files:**
- Create: `app/Filament/Resources/Units/UnitResource.php`
- Create: `app/Filament/Resources/Units/Schemas/UnitForm.php`
- Create: `app/Filament/Resources/Units/Tables/UnitsTable.php`
- Create: `app/Filament/Resources/Units/Pages/{ListUnits,CreateUnit,EditUnit}.php`

**Interfaces:**
- Consumes: `Unit` model (Task 1).
- Produces: Filament CRUD at `/admin/units`, nav group "Inventory" sort 0.

- [ ] **Step 1: Scaffold via artisan**

Run: `php artisan make:filament-resource Unit --view` — actually this repo doesn't use `--view`; check existing pattern first with `php artisan make:filament-resource Unit` then delete any `ViewUnit` page it creates if RawMaterial/StockMovement resources don't have a View page (they only have List/Create/Edit).

- [ ] **Step 2: Edit `app/Filament/Resources/Units/Schemas/UnitForm.php`**

```php
<?php

namespace App\Filament\Resources\Units\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('symbol')
                    ->required()
                    ->unique(ignoreRecord: true),
            ]);
    }
}
```

- [ ] **Step 3: Edit `app/Filament/Resources/Units/Tables/UnitsTable.php`**

```php
<?php

namespace App\Filament\Resources\Units\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UnitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('symbol')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

- [ ] **Step 4: Edit `app/Filament/Resources/Units/UnitResource.php`**

```php
<?php

namespace App\Filament\Resources\Units;

use App\Filament\Resources\Units\Pages\CreateUnit;
use App\Filament\Resources\Units\Pages\EditUnit;
use App\Filament\Resources\Units\Pages\ListUnits;
use App\Filament\Resources\Units\Schemas\UnitForm;
use App\Filament\Resources\Units\Tables\UnitsTable;
use App\Models\Unit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';
    protected static ?int $navigationSort = 0;

    public static function form(Schema $schema): Schema
    {
        return UnitForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnitsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnits::route('/'),
            'create' => CreateUnit::route('/create'),
            'edit' => EditUnit::route('/{record}/edit'),
        ];
    }
}
```

(Pages `ListUnits`/`CreateUnit`/`EditUnit` are the scaffolded defaults — leave as generated, matching `ListRawMaterials`/`CreateRawMaterial`/`EditRawMaterial` shape.)

- [ ] **Step 5: Manually verify in browser**

Run: `php artisan serve` (or existing dev server), visit `/admin/units`, create a unit (name "Kilogram", symbol "kg"), confirm it lists and edits.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/Units
git commit -m "feat: add Filament resource for managing units"
```

---

### Task 6: `RecipeResource` (Filament)

**Files:**
- Create: `app/Filament/Resources/Recipes/RecipeResource.php`
- Create: `app/Filament/Resources/Recipes/Schemas/RecipeForm.php`
- Create: `app/Filament/Resources/Recipes/Tables/RecipesTable.php`
- Create: `app/Filament/Resources/Recipes/Pages/{ListRecipes,CreateRecipe,EditRecipe}.php`

**Interfaces:**
- Consumes: `Recipe`, `Product`, `RawMaterial`, `Unit` models.
- Produces: Filament CRUD at `/admin/recipes`, nav group "Inventory" sort 3.

- [ ] **Step 1: Scaffold via artisan**

Run: `php artisan make:filament-resource Recipe`

- [ ] **Step 2: Edit `app/Filament/Resources/Recipes/Schemas/RecipeForm.php`**

```php
<?php

namespace App\Filament\Resources\Recipes\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RecipeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('raw_material_id')
                    ->relationship('rawMaterial', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('unit_id')
                    ->relationship('unit', 'symbol')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('quantity')
                    ->required()
                    ->numeric(),
            ]);
    }
}
```

- [ ] **Step 3: Edit `app/Filament/Resources/Recipes/Tables/RecipesTable.php`**

```php
<?php

namespace App\Filament\Resources\Recipes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecipesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable(),
                TextColumn::make('rawMaterial.name')
                    ->label('Raw Material')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unit.symbol')
                    ->label('Unit'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

- [ ] **Step 4: Edit `app/Filament/Resources/Recipes/RecipeResource.php`**

```php
<?php

namespace App\Filament\Resources\Recipes;

use App\Filament\Resources\Recipes\Pages\CreateRecipe;
use App\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Filament\Resources\Recipes\Schemas\RecipeForm;
use App\Filament\Resources\Recipes\Tables\RecipesTable;
use App\Models\Recipe;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecipeResource extends Resource
{
    protected static ?string $model = Recipe::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return RecipeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecipesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecipes::route('/'),
            'create' => CreateRecipe::route('/create'),
            'edit' => EditRecipe::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 5: Manually verify in browser**

Visit `/admin/recipes`, create a recipe picking an existing product/raw material/unit, confirm it lists with product/raw material names.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/Recipes
git commit -m "feat: add Filament resource for managing product recipes"
```

---

### Task 7: Update `RawMaterialResource` to use `unit_id` select

**Files:**
- Modify: `app/Filament/Resources/RawMaterials/Schemas/RawMaterialForm.php`
- Modify: `app/Filament/Resources/RawMaterials/Tables/RawMaterialsTable.php`

**Interfaces:**
- Consumes: `RawMaterial::unit()` relation (Task 2).

- [ ] **Step 1: Replace unit TextInput with Select in `RawMaterialForm.php`**

```php
<?php

namespace App\Filament\Resources\RawMaterials\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RawMaterialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('unit_id')
                    ->relationship('unit', 'symbol')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('stock')
                    ->required()
                    ->numeric()
                    ->default(0.0),
            ]);
    }
}
```

- [ ] **Step 2: Replace unit TextColumn with relation column in `RawMaterialsTable.php`**

Change:
```php
                TextColumn::make('unit')
                    ->searchable(),
```
to:
```php
                TextColumn::make('unit.symbol')
                    ->label('Unit')
                    ->searchable(),
```

- [ ] **Step 3: Manually verify in browser**

Visit `/admin/raw-materials`, create/edit a raw material, confirm unit select shows units from Task 5 and table shows the symbol.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Resources/RawMaterials/Schemas/RawMaterialForm.php app/Filament/Resources/RawMaterials/Tables/RawMaterialsTable.php
git commit -m "feat: switch raw material unit field to Unit relation select"
```

---

### Task 8: Full regression check

**Files:** none (verification only)

- [ ] **Step 1: Fresh migrate + full test suite**

Run: `php artisan migrate:fresh && php artisan test`
Expected: all tests PASS, no migration errors

- [ ] **Step 2: Spot-check nav order in browser**

Visit `/admin`, confirm "Inventory" group order is Units, Raw Materials, Stock Movements, Recipes.
