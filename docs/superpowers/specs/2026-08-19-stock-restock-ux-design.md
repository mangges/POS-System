# Restock (Stock Increase) UX

## Problem

`stock_movements` mencatat pergerakan stok (`in`/`out`), tapi tidak ada
mekanisme yang benar-benar mengubah `raw_materials.stock` atau
`products.stock` saat movement dibuat — kolom stock masih harus diedit
manual. `StockMovementResource` yang ada sekarang juga tidak nyaman dipakai:
`reference_id`/`reference_type` adalah `TextInput` bebas, admin harus tahu ID
raw material/product secara manual. Decrease-on-order-completed (event
trigger saat order selesai) akan dibangun terpisah dan diasumsikan cuma akan
insert baris `StockMovement` tipe `out` — jadi mekanisme sync stock harus
sudah otomatis di level model, bukan ditulis ulang di setiap fitur yang
menambah movement.

## Scope

- `StockMovement::reference()` jadi `morphTo()` beneran + morph map
  terdaftar (`product`, `raw_material`), bukan if/else manual.
- Observer: setiap `StockMovement` dibuat, otomatis
  increment/decrement kolom `stock` di reference-nya (atomic SQL
  increment/decrement, bukan read-modify-write).
- Relation manager riwayat + form tambah movement, dipasang di
  `RawMaterialResource` (semua record) dan `ProductResource` (cuma
  record dengan `has_recipe = false`).
- Movement bersifat append-only: relation manager tidak punya Edit/Delete.
- `StockMovementResource` standalone jadi read-only (cabut halaman
  Create/Edit) — tetap ada sebagai laporan global lintas item.

Out of scope (belum diminta):
- Decrease-on-order-completed trigger itu sendiri — hanya dipastikan
  mekanisme sync-nya compatible (insert `StockMovement` tipe `out` otomatis
  kepotong via observer yang sama).
- Validasi stock tidak boleh negatif — decrement tetap jalan meski hasil
  jadi minus, ditambahkan nanti kalau dibutuhkan.
- Restock massal (bulk action pilih banyak item sekaligus).
- Reverse/void movement — koreksi salah input dilakukan dengan movement
  baru, bukan edit/hapus yang lama.

## Design

### 1. Proper polymorphic relation

`AppServiceProvider::boot()`:

```php
Relation::morphMap([
    'product' => Product::class,
    'raw_material' => RawMaterial::class,
]);
```

`StockMovement::reference()` diganti jadi:

```php
public function reference(): MorphTo
{
    return $this->morphTo();
}
```

`RawMaterial` dan `Product` masing-masing dapat relasi baru:

```php
public function stockMovements(): MorphMany
{
    return $this->morphMany(StockMovement::class, 'reference');
}
```

### 2. Auto stock sync (Observer)

`app/Observers/StockMovementObserver.php`, didaftarkan di
`AppServiceProvider::boot()` via `StockMovement::observe(StockMovementObserver::class)`:

```php
public function created(StockMovement $movement): void
{
    $column = $movement->type === 'in' ? 'increment' : 'decrement';

    $movement->reference()->{$column}('stock', $movement->quantity);
}
```

Ini satu-satunya tempat yang mengubah `stock`. Fitur lain (restock UI di sini,
decrease-on-order-completed nanti) cukup insert `StockMovement` row — stock
ke-sync otomatis.

### 3. Relation manager

`app/Filament/Resources/RawMaterials/RelationManagers/StockMovementsRelationManager.php`
(dan didaftarkan juga lewat `ProductResource::getRelations()`, relation name
sama `stockMovements`).

Tabel: `type` (badge, warna `in`=success/`out`=danger), `quantity`,
`notes`, `user.name`, `created_at`. Header action "Add Movement":
modal berisi `Select::make('type')` (`in`/`out`, default `in`),
`TextInput::make('quantity')` (numeric, required), `Textarea::make('notes')`.
`user_id` diisi otomatis dari `auth()->id()` saat create (`mutateFormDataUsing`
atau `Filament::auth()->id()`), tidak perlu dipilih manual.

Untuk `ProductResource`, relation manager di-guard:

```php
public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
{
    return ! $ownerRecord->has_recipe;
}
```

### 4. Append-only

Relation manager table cuma punya `ViewAction`/tanpa action edit — tidak ada
`EditAction`/`DeleteAction`/`DeleteBulkAction`. Kalau data movement salah
input, solusinya bikin movement koreksi baru (mis. `out` untuk mengurangi
balik), bukan mengubah baris lama.

### 5. `StockMovementResource` jadi read-only global report

`getPages()` cuma sisa `index`. `ListStockMovementsTable` ditambah kolom
untuk menampilkan nama reference (`reference.name`) supaya laporan lintas
item tetap kebaca tanpa perlu resolve manual ID.

## Testing

- Model test: `StockMovement` type `in`/`out` meng-increment/decrement
  `stock` milik `RawMaterial` dan `Product` (via observer).
- Filament test: relation manager di `EditRawMaterial` — create movement via
  `Add Movement`, assert stock ter-update dan row muncul di tabel.
- Filament test: relation manager tidak muncul di halaman produk dengan
  `has_recipe = true`.
- `StockMovementResource` index masih bisa diakses; halaman create/edit
  return 404 (route sudah tidak terdaftar).
