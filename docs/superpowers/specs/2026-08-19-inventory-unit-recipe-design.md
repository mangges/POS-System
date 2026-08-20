# Unit & Recipe untuk Inventory Stocking

## Problem

`raw_materials.unit` masih string bebas (bisa typo, tidak konsisten: "gram" vs
"gr" vs "g"). Tidak ada relasi antara `products` dan `raw_materials` — produk
dengan `has_recipe = true` tidak punya cara mendefinisikan bahan baku apa
saja yang terpakai dan berapa jumlahnya, jadi stok raw material tidak bisa
dihitung otomatis. `stock_movements` juga belum punya index untuk query
report by product/raw_material.

## Scope

- Model `Unit` (satuan master: kg, gram, pcs, dll), dipakai oleh
  `raw_materials` dan `recipes`.
- Model `Recipe`: definisi komposisi bahan baku per produk
  (`product_id`, `raw_material_id`, `unit_id`, `quantity`).
- Filament resource untuk `Unit` dan `Recipe` (grup nav "Inventory").
- Index di `stock_movements` untuk report grouping.

Out of scope (belum diminta):
- Auto-deduct stok `raw_materials` saat order dibuat/completed. Wiring
  `OrderItem` → `Recipe` → `StockMovement` menyusul saat dibutuhkan.
- Konversi antar satuan (mis. kg ke gram). `Recipe.unit_id` dan
  `RawMaterial.unit_id` diasumsikan konsisten manual oleh admin.

## Aturan stok (konfirmasi user)

- Produk `has_recipe = false`: stok dikelola langsung di `products.stock`.
- Produk `has_recipe = true`: `products.stock` tidak dipakai; stok efektif
  berasal dari `raw_materials.stock` lewat `Recipe`.
- `stock_movements` tetap mencatat semua pergerakan (baik reference ke
  product maupun raw_material), tanpa dibedakan has_recipe atau tidak.

## Data model

**units**

| column | type | notes |
|---|---|---|
| `name` | string | e.g. "Kilogram" |
| `symbol` | string, unique | e.g. "kg" |

**raw_materials** (migration alter)

- Tambah `unit_id` FK → `units`, nullable dulu → backfill dari string `unit`
  existing (buat/​match `Unit` per nilai distinct) → set `unit_id` → drop
  kolom `unit` lama.

**recipes**

| column | type | notes |
|---|---|---|
| `product_id` | FK → products, cascade delete | |
| `raw_material_id` | FK → raw_materials, cascade delete | |
| `unit_id` | FK → units | |
| `quantity` | decimal(10,2) | jumlah raw material terpakai per 1 produk terjual |

Unique constraint (`product_id`, `raw_material_id`) — satu baris per pasangan.

**stock_movements** (migration alter)

- Tambah composite index (`reference_type`, `reference_id`).

## Models

- `Unit`: `hasMany(RawMaterial)`, `hasMany(Recipe)`.
- `RawMaterial`: `belongsTo(Unit)`, `hasMany(Recipe)`.
- `Recipe`: `belongsTo(Product)`, `belongsTo(RawMaterial)`, `belongsTo(Unit)`.
- `Product`: tambah `hasMany(Recipe, 'product_id')` sebagai `recipes()`.

## Filament

- `UnitResource`: form/table name + symbol. Nav group "Inventory", sort 0.
- `RecipeResource`: form select product, select raw_material, select unit,
  quantity. Table: product name, raw_material name, quantity, unit symbol.
  Nav group "Inventory", sort 3 (setelah RawMaterial=1, StockMovement=2).
- `RawMaterialForm`/`RawMaterialsTable`: ganti `TextInput::make('unit')` jadi
  `Select::make('unit_id')->relationship('unit', 'symbol')`.

## Testing

- `php artisan migrate:fresh` jalan tanpa error (backfill migration diuji di
  DB kosong — tidak ada data existing untuk migrate, jadi backfill jalan
  pada dataset kosong).
- Buka tiap Filament resource baru (Unit, Recipe) di browser, cek create/edit
  form tersimpan benar.
