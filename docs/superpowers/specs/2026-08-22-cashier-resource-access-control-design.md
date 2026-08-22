# Cashier Resource Access Control

## Problem

Semua Filament Resource & Page sekarang bisa diakses oleh admin maupun
cashier tanpa pembeda apa pun — `User::canAccessPanel()` cuma `return true`
statis, dan tidak ada satu pun `canAccess()`/Policy yang ngecek role. Ini
masalah karena beberapa resource sensitif (akun staff, laporan omzet,
config sistem) seharusnya cuma boleh diakses admin.

Tapi banyak UMKM cuma punya satu device dengan satu akun cashier yang
login. Kalau ada loading barang atau kebutuhan inventory lain di jam
kerja, gak realistis nyuruh cashier logout terus login pakai akun admin.
Jadi pembatasan gak bisa cuma "boleh"/"gak boleh" — sebagian resource
butuh jalur elevasi sementara pakai PIN admin, tanpa harus ganti akun.

## Scope

- Ganti sistem role dari kolom enum `users.role` ke
  `spatie/laravel-permission`, dengan role `admin` dan `cashier`.
- Bagi resource jadi 3 tier akses (lihat Design §3–§5).
- Tier 2 (resource inventory) bisa dibuka cashier lewat PIN admin,
  per-resource: buka resource lain otomatis nyabut akses resource
  sebelumnya, harus PIN lagi kalau mau balik.
- Tier 3 (resource admin-only) dikunci total buat cashier, gak ada jalur
  PIN sama sekali.
- Migrasi data: user existing dengan `role` enum lama otomatis dapat
  spatie role yang sesuai, kolom `role` lama dihapus setelah semua kode
  yang bergantung padanya (`Login.php`, `UserForm`, `UsersTable`)
  diupdate.

Out of scope (belum diminta):

- Role ketiga atau permission granular di luar 3 tier ini (misal role
  "kasir-gudang" terpisah) — kalau nanti toko punya staff dengan
  tanggung jawab lebih spesifik, baru ditambah.
- Expiry waktu buat PIN unlock (misal auto-terkunci abis 15 menit
  walau belum pindah resource) — sesuai keputusan, unlock cuma hilang
  kalau pindah ke resource Tier 2 lain atau login ulang, bukan karena
  waktu habis.
- Rate limiting / lockout percobaan PIN salah berulang — belum
  diminta, PIN admin juga cuma dipakai staff internal.
- Audit log siapa yang PIN-unlock resource apa kapan — bisa nyusul
  kalau dibutuhkan buat investigasi selisih stok.

## Design

### 1. Migrasi ke spatie/laravel-permission

`composer require spatie/laravel-permission`, publish & jalankan
migration bawaannya (`roles`, `permissions`, `model_has_roles`,
`model_has_permissions`, `role_has_permissions`). `App\Models\User`
pakai trait `Spatie\Permission\Traits\HasRoles`.

Migration data baru (`assign_roles_to_existing_users`) — jalan setelah
migration tabel spatie, sebelum kolom `role` lama didrop:

```php
public function up(): void
{
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'cashier']);

    foreach (User::all() as $user) {
        $user->assignRole($user->role); // 'admin' | 'cashier' dari kolom enum lama
    }
}
```

Setelah itu, migration terpisah drop kolom `role` dari `users` (bagian
dari plan yang sama, dieksekusi setelah kode di bawah ini beres
diupdate supaya gak ada yang masih baca kolom lama).

**Kode yang berubah karena kolom `role` dihapus:**

- [Login.php](app/Livewire/Auth/Login.php) — `$user->role === 'admin'`
  (baris 54, PIN login) dan `in_array($user->role, ['admin'])` (baris
  75, email login) diganti `$user->hasRole('admin')`.
- [UserForm.php](app/Filament/Resources/Users/Schemas/UserForm.php) —
  `Select::make('role')` yang sebelumnya binding langsung ke kolom,
  diganti field non-DB yang di-`dehydrated(false)`, nilainya di-load
  dari `$record?->getRoleNames()->first()` dan disimpan lewat
  `afterSave` hook (`$record->syncRoles([$state])`).
- [UsersTable.php](app/Filament/Resources/Users/Tables/UsersTable.php)
  — `TextColumn::make('role')` diganti
  `TextColumn::make('roles.name')` (atau state callback
  `fn ($record) => $record->getRoleNames()->first()`).
- [UserSeeder.php](database/seeders/UserSeeder.php) — insert lewat
  `DB::table('users')` diganti `User::create()` + `assignRole()`, biar
  ikut mekanisme spatie dari awal (insert mentah gak bisa isi pivot
  role).

### 2. Permission list

7 permission, didaftarkan di seeder yang sama dengan role:

- Tier 2 (satu per resource, dipakai buat PIN-unlock):
  `access-categories`, `access-products`, `access-raw-materials`,
  `access-recipes`, `access-stock-movements`, `access-units`.
- Tier 3 (satu permission buat semua resource admin-only — gak butuh
  granular karena selamanya admin-only, gak ada jalur unlock):
  `access-admin-only`.

Role `cashier` tidak diberi permission apa pun secara default. Role
`admin` juga tidak perlu di-assign permission satu-satu — lihat §admin
bypass di bawah.

**Admin bypass** — di `AppServiceProvider::boot()`:

```php
Gate::before(fn (User $user) => $user->hasRole('admin') ? true : null);
```

Admin otomatis lolos setiap `can()` check, termasuk permission baru
yang ditambah nanti tanpa perlu sinkronisasi manual.

### 3. Tier 1 — akses penuh, tanpa gate

`OrderResource`, `OrderItemResource`, `PaymentResource`,
`CustomerResource`, `TableResource`, `Dashboard`, `PosCashier`,
`PosReceipt` — tidak ada perubahan `canAccess()`, tetap seperti
sekarang (default Filament: siapa saja yang bisa masuk panel boleh
akses).

### 4. Tier 2 — PIN gate (bisa diakses, wajib PIN admin tiap masuk)

`CategoryResource`, `ProductResource`, `RawMaterialResource`,
`RecipeResource`, `StockMovementResource`, `UnitResource`.

`canAccess()` resource-resource ini TETAP default (true) — nav item
harus tetap kelihatan buat cashier, biar mereka tau resource itu ada
dan bisa minta akses. Yang benar-benar nge-gate itu middleware baru
(§6), bukan `canAccess()`.

Permission yang dicek per resource ini: `access-{slug}` (slug =
`categories`, `products`, `raw-materials`, `recipes`,
`stock-movements`, `units` — sesuai slug default Filament dari nama
model).

### 5. Tier 3 — hard lock, tanpa jalur PIN

`UserResource`, `Reports\SalesReport`, `Reports\ProductReport`,
`PaymentMethodSettings`, `ReceiptSettings`.

```php
public static function canAccess(): bool
{
    return auth()->user()->can('access-admin-only');
}
```

Cashier gak punya permission ini dan gak ada mekanisme buat dapetin —
nav item hilang total, akses URL langsung ketemu 403 bawaan Filament.

### 6. Middleware `EnsureResourcePinUnlocked`

`app/Http/Middleware/EnsureResourcePinUnlocked.php`, didaftarkan di
[AdminPanelProvider](app/Providers/Filament/AdminPanelProvider.php)
lewat `->authMiddleware([Authenticate::class,
EnsureResourcePinUnlocked::class])` (butuh user sudah login, jadi
ditaruh setelah `Authenticate::class`, bukan di `->middleware()` yang
juga kena halaman login).

```php
protected const array PROTECTED = [
    'categories' => 'access-categories',
    'products' => 'access-products',
    'raw-materials' => 'access-raw-materials',
    'recipes' => 'access-recipes',
    'stock-movements' => 'access-stock-movements',
    'units' => 'access-units',
];

public function handle(Request $request, Closure $next): Response
{
    $user = auth()->user();
    $routeName = $request->route()?->getName() ?? '';

    foreach (self::PROTECTED as $slug => $permission) {
        if (str_contains($routeName, "resources.{$slug}.") && $user?->cannot($permission)) {
            return redirect(AdminPinGate::getUrl(['resource' => $slug, 'redirect' => $request->fullUrl()]));
        }
    }

    return $next($request);
}
```

Karena middleware ini jalan sebelum Livewire full-page component
resource itu mount, redirect ke PIN gate selalu duluan ketimbang 403
bawaan Filament (yang mana toh gak akan kepicu karena `canAccess()`
Tier 2 tetap `true`).

### 7. Page `AdminPinGate`

`app/Filament/Pages/AdminPinGate.php`,
`shouldRegisterNavigation(): false` (gak muncul di sidebar, cuma
ketemu lewat redirect). Form: `TextInput::make('pin')` numeric, 6
digit. Terima query param `resource` (slug Tier 2 yang diminta) dan
`redirect` (URL tujuan setelah berhasil).

Submit:

```php
$admin = User::role('admin')->where('pin', $this->pin)->first();

if (! $admin) {
    Notification::make()->danger()->title('PIN salah')->send();
    return;
}

auth()->user()->syncPermissions(["access-{$this->resource}"]);

$this->redirect($this->redirectUrl);
```

`syncPermissions()` mengganti seluruh direct permission user yang
sedang login dengan cuma satu permission Tier 2 ini — otomatis nyabut
permission Tier 2 lain yang sebelumnya aktif. Ini yang bikin balik ke
resource Tier 2 sebelumnya perlu PIN lagi, walaupun barusan udah
pernah unlock.

Perbandingan PIN pakai kolom `pin` (`string(6)`, plaintext) yang sudah
ada di `users` — pola sama seperti
[Login.php](app/Livewire/Auth/Login.php) PIN-login yang sudah
berjalan (`where('pin', $value)`), gak perlu hashing baru atau kolom
tambahan.

### 8. Reset permission saat login

Di kedua jalur `Login.php` (`loginWithPin()` dan login email), tepat
setelah `Auth::login($user)` sukses:

```php
$user->syncPermissions([]);
```

Mencegah permission Tier 2 dari sesi sebelumnya nyangkut kalau user
logout tanpa sempat pindah resource — setiap login baru selalu mulai
dalam keadaan terkunci penuh dari Tier 2.

## Testing

- Migration data: user dengan `role = admin` lama dapat spatie role
  `admin`, `role = cashier` dapat `cashier`.
- `Gate::before` — user ber-role admin lolos `can()` untuk permission
  yang bahkan belum pernah di-assign eksplisit.
- Tier 1 resource: cashier (tanpa permission apa pun) tetap bisa akses
  index/create/edit.
- Tier 3 resource: `canAccess()` `false` untuk cashier, `true` untuk
  admin.
- Tier 2 resource, cashier belum unlock: mengakses route resource
  itu redirect ke `AdminPinGate` dengan query param `resource` yang
  benar.
- `AdminPinGate`: PIN salah → notifikasi error, tetap di halaman, gak
  ada permission yang berubah. PIN admin benar → permission
  `access-{resource}` ke-assign ke cashier yang login, redirect ke URL
  asal berhasil.
- Skenario pindah resource: cashier unlock `raw-materials` (permission
  ke-assign) → cashier akses `products` (diminta PIN lagi karena beda
  permission) → cashier unlock `products` → cashier balik ke
  `raw-materials` (diminta PIN lagi, karena `syncPermissions` waktu
  unlock `products` udah nyabut `access-raw-materials`).
- Login baru: cashier yang sebelumnya sempat unlock salah satu Tier 2
  lalu logout, begitu login lagi permissionnya kosong (harus PIN lagi
  dari nol).

## Implementation Guidelines

Saat eksekusi plan dari spec ini, pakai skill berikut:

- **Build (backend/logic):** `superpowers` (workflow plan → TDD →
  verification), `ponytail` (solusi paling lazy yang tetap benar,
  hindari over-engineering), `caveman` (komunikasi ringkas selama
  proses build).
- **UI/UX (halaman `AdminPinGate`):** `ui-ux-pro-max`, `frontend-design`.
- **Commit:** `commit-work` — commit message satu baris, tanpa
  co-author.
