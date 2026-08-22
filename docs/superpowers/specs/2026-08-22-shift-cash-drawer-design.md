# Shift & Cash Drawer

## Problem

Order sekarang cuma tersimpan `user_id` (siapa kasirnya), tanpa konsep sesi
kerja. Gak ada cara buat tau: kasir mulai jaga jam berapa dengan modal awal
berapa, berapa total cash yang seharusnya ada di laci pas dia selesai, dan
apakah uang fisik cocok sama catatan sistem. Kalau ada selisih kas, gak ada
jejaknya sama sekali. Setiap kasir jaga di sesi masing-masing (bisa lebih
dari satu kasir aktif barengan), jadi pelacakan harus per-user, bukan satu
sesi global per toko.

## Scope

- Kasir wajib buka shift (isi modal awal) sebelum bisa transaksi di halaman
  Cashier. Satu user cuma boleh punya satu shift `open` di waktu yang sama.
- Cash in/out selama shift berjalan (nominal + alasan), misal setor ke bank
  atau tambah modal — ikut masuk ke perhitungan expected cash.
- Tutup shift: sistem hitung `expected_cash` otomatis, kasir input
  `actual_cash` hasil hitung fisik, selisih dihitung dan disimpan. Kasir
  boleh kasih catatan kalau ada selisih. Tutup shift tidak butuh approval
  siapapun — kasir yang buka, kasir yang tutup, sendiri.
- Non-cash (QRIS, transfer/kartu) ditampilkan sebagai info total penjualan
  non-cash selama shift, tapi tidak masuk hitungan fisik laci (gak ada uang
  fisik yang perlu dicocokkan buat metode itu).
- Admin (Filament) bisa lihat riwayat semua shift lintas kasir: waktu buka
  /tutup, modal, expected vs actual, selisih, plus riwayat cash
  in/out per shift.
- Shift Report: halaman report baru (pola sama seperti `SalesReport`/
  `ProductReport` yang sudah ada), rekap shift per periode/kasir dengan
  export CSV & PDF.

Out of scope (belum diminta):
- Approval/lock kalau selisih di atas toleransi tertentu — kasir selalu bisa
  nutup shift sendiri, selisih cuma tercatat buat direview admin nanti.
- Refund/void transaksi masuk ke perhitungan expected cash — fitur refund
  sendiri belum ada di sistem, jadi expected cash cuma dari penjualan cash
  yang berhasil (`success`).
- Print laporan tutup shift (struk rekonsiliasi) — nyusul kalau dibutuhkan.
- Multi-outlet/shift lintas cabang — sistem ini masih single-outlet.

## Design

### 1. Model & migration

`shifts` table:

```php
Schema::create('shifts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->decimal('opening_cash', 12, 2);
    $table->decimal('expected_cash', 12, 2)->nullable();
    $table->decimal('actual_cash', 12, 2)->nullable();
    $table->decimal('difference', 12, 2)->nullable();
    $table->string('status')->default('open'); // open | closed
    $table->text('note')->nullable();
    $table->timestamp('opened_at');
    $table->timestamp('closed_at')->nullable();
    $table->timestamps();
});
```

`cash_movements` table:

```php
Schema::create('cash_movements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
    $table->string('type'); // in | out
    $table->decimal('amount', 12, 2);
    $table->string('reason');
    $table->foreignId('created_by')->constrained('users');
    $table->timestamps();
});
```

`orders` table dapat kolom baru: `shift_id` (nullable, `foreignId` ke
`shifts`) — order yang dibuat selama shift jalan otomatis kesimpen
`shift_id`-nya.

`App\Enum\Shifts\ShiftStatus` (`Open`, `Closed`) dan
`App\Enum\Shifts\CashMovementType` (`In`, `Out`), pola sama seperti
`OrderStatus`/`PaymentMethod` yang sudah ada.

Model `Shift` (relasi `user()`, `cashMovements()`, `orders()`) dan
`CashMovement` (relasi `shift()`, `creator()` ke `User`) di
`app/Models/`, ikut konvensi model lain di project ini.

### 2. Buka shift (blocking gate di Cashier)

`app/Livewire/Pos/Cashier.php` di `boot()`/`mount()` cek
`Shift::where('user_id', auth()->id())->where('status', 'open')->first()`
dan simpan ke property `?Shift $activeShift`.

Kalau `activeShift` null, view `cashier` render blocking screen "Buka
Shift" (bukan modal — kasir belum boleh lihat POS-nya sama sekali) berisi
input `opening_cash`. Submit → `ShiftService::open(user, openingCash)`
bikin row `Shift` baru status `open`, `opened_at = now()`.

Kalau `activeShift` ada, halaman Cashier render normal seperti sekarang,
plus indikator kecil (misal di header) nunjukin shift lagi aktif +
tombol "Cash In/Out" dan "Tutup Shift".

### 3. Cash in/out

Modal kecil dari Cashier: pilih `in`/`out`, input `amount` (wajib > 0) dan
`reason` (wajib diisi). Submit bikin row `CashMovement` terikat ke
`activeShift`. Tidak ada edit/delete — salah catat berarti bikin
movement koreksi baru (append-only, sama seperti pola
`StockMovement`).

### 4. Tutup shift

`ShiftService::close(Shift $shift, float $actualCash, ?string $note)`:

```php
$cashSales = Payment::whereHas('order', fn ($q) => $q->where('shift_id', $shift->id))
    ->where('payment_method', PaymentMethod::Cash->value)
    ->where('status', PaymentStatus::Success->value)
    ->sum('amount');

$cashIn = $shift->cashMovements()->where('type', CashMovementType::In->value)->sum('amount');
$cashOut = $shift->cashMovements()->where('type', CashMovementType::Out->value)->sum('amount');

$expectedCash = $shift->opening_cash + $cashSales + $cashIn - $cashOut;
$difference = $actualCash - $expectedCash;

$shift->update([
    'expected_cash' => $expectedCash,
    'actual_cash' => $actualCash,
    'difference' => $difference,
    'note' => $note,
    'status' => ShiftStatus::Closed->value,
    'closed_at' => now(),
]);
```

Non-cash total (buat ditampilkan info doang, gak masuk `expected_cash`):
`Payment` dengan `payment_method` != `cash` dan `status = success`, order
`shift_id` = shift ini.

Form tutup shift di Cashier nampilin `expected_cash` (read-only, hasil
hitung di atas) berdampingan sama input `actual_cash`, dan textarea
`note` (opsional, disarankan diisi kalau ada selisih). Submit langsung
nutup — tidak ada approval step. Setelah tutup, Cashier balik ke state
"belum ada shift aktif" (blocking screen buka shift lagi).

### 5. Guard satu shift aktif per user

`ShiftService::open()` cek dulu apakah user masih punya shift `open` —
kalau ada, lempar exception/validation error, gak bikin baru. Ini juga
yang bikin kasir yang browser-nya ke-close di tengah shift, pas login
lagi ketemu `activeShift` yang sama (bukan dipaksa buka baru).

### 6. Admin — Filament `ShiftResource`

`app/Filament/Resources/Shifts/` (pola sama seperti resource lain:
`Pages`, `Schemas`, `Tables`). Read-only (tidak ada Create/Edit manual —
shift cuma dibuat/ditutup lewat Cashier): kolom kasir (`user.name`),
`opened_at`, `closed_at`, `opening_cash`, `expected_cash`, `actual_cash`,
`difference` (badge warna: hijau kalau 0, merah kalau selisih), `status`.

Relation manager `CashMovementsRelationManager` di halaman View Shift:
list semua cash in/out (type, amount, reason, creator, waktu),
read-only juga.

### 7. Shift Report page

`app/Filament/Pages/Reports/ShiftReport.php`, dibuat persis mengikuti pola
`SalesReport`/`ProductReport` yang sudah ada (`use HasReportPeriod`,
navigation group `Reports`, form filter periode + kasir, `getRows()`,
export CSV & PDF, blade view di
`resources/views/filament/pages/reports/shift-report.blade.php`).

Filter tambahan di luar `HasReportPeriod` (period type + date range yang
sudah ada di trait): `Select::make('user_id')` opsional, buat nyaring per
kasir.

`getRows()` ambil `Shift::whereBetween('opened_at', [$start, $end])`
(ikut `user_id` filter kalau diisi), per shift hitung baris:

- `user` — nama kasir
- `opened_at`, `closed_at`
- `opening_cash`
- `cash_sales` — total `Payment` cash sukses selama shift itu (query sama
  seperti di `ShiftService::close()`)
- `non_cash_sales` — total `Payment` non-cash sukses selama shift itu
- `cash_in`, `cash_out` — dari `cashMovements()`
- `expected_cash`, `actual_cash`, `difference` — kolom yang udah tersimpan
  di shift (kalau masih `open`, ditampilkan sebagai "-" / belum tutup)

## Testing

- `ShiftService::open()`: bikin shift `open` dengan `opening_cash` yang
  benar; gagal (validation error) kalau user masih punya shift `open`
  lain.
- Cashier component: tanpa shift aktif, render blocking "Buka Shift"
  view, bukan POS. Dengan shift aktif, render POS normal.
- Order yang dibuat lewat Cashier saat shift aktif otomatis dapat
  `shift_id` yang benar.
- `CashMovement`: create `in`/`out` ikut ke perhitungan `expected_cash`
  saat shift ditutup (assert nilai akhir).
- `ShiftService::close()`: `expected_cash` = opening + cash sales sukses
  + cash in - cash out (dites dengan campuran payment cash & non-cash,
  non-cash tidak ikut terhitung). `difference` = actual - expected.
- Filament `ShiftResource`: index bisa diakses, tidak ada route
  create/edit terdaftar (404).
- `ShiftReport` page: `getRows()` menghasilkan angka yang benar (cash
  sales, non-cash sales, cash in/out, expected/actual/difference) untuk
  shift dalam rentang periode filter; filter `user_id` menyaring dengan
  benar; export CSV/PDF bisa diakses tanpa error.

## Implementation Guidelines

Saat eksekusi plan dari spec ini, pakai skill berikut:

- **Build (backend/logic):** `superpowers` (workflow plan → TDD →
  verification), `ponytail` (solusi paling lazy yang tetap benar, hindari
  over-engineering), `caveman` (komunikasi ringkas selama proses build).
- **UI/UX (Cashier blocking screen, modal cash in/out, form tutup shift,
  Filament resource & report page):** `ui-ux-pro-max`, `frontend-design`.
- **Commit:** `commit-work` — commit message satu baris, tanpa
  co-author.
