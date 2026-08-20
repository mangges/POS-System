# Receipt Customization Settings Page

## Problem

Struk (receipt) yang dicetak di POS punya konten toko yang di-hardcode
langsung di `resources/views/livewire/pos/receipts/receipt.blade.php`: nama
toko "Pos cafee", alamat, telp, website, teks footer, dan label PPN "PPN
(11%)" sebagai string literal. Merchant tidak bisa mengubah info toko atau
mematikan bagian QR e-receipt tanpa edit kode.

## Scope

- Admin settings page baru untuk atur: nama toko, alamat, telp, website,
  logo, footer text, dan toggle tampil/sembunyi QR e-receipt.
- Struk (`receipt.blade.php`) baca dari settings ini, bukan hardcoded.
- Label PPN di struk dihitung dari data order (`tax / subtotal * 100`),
  bukan string `"11%"` yang ditulis manual — supaya tetap benar kalau tarif
  pajak berubah di masa depan.

Out of scope:
- Field lain di struk (label "Kasir", "Pelanggan", item list format) tetap
  fixed di kode — sesuai jawaban user, cukup info toko + logo + footer + QR.
- Multi-tenant / multi-cabang (banyak baris settings). Satu toko = satu baris
  settings, sesuai kebutuhan aplikasi ini sekarang.
- Mengubah tarif pajak itu sendiri (`CartCalculatorService::$taxRate` tetap
  0.11) — hanya label di struk yang dihitung dinamis dari data.

## Data model

Migration baru `receipt_settings`, single-row table:

| column | type | notes |
|---|---|---|
| `store_name` | string | required, default "Pos Cafee" |
| `address` | string, nullable | |
| `phone` | string, nullable | |
| `website` | string, nullable | |
| `logo_path` | string, nullable | path relatif di disk `public` |
| `footer_text` | text, nullable, max 500 char | ditulis bebas multi-baris, tiap baris jadi `<p>` terpisah di struk |
| `show_qr` | boolean, default true | sembunyikan blok QR + teks "Scan untuk e-receipt" kalau false |

`App\Models\ReceiptSetting` — plain Eloquent model, `fillable` semua kolom
di atas, cast `show_qr` → boolean. Helper `static::current(): self` —
`static::first() ?? new static(['store_name' => 'Pos Cafee', 'show_qr' => true])`,
jadi caller selalu dapat objek walau belum pernah disave (baris pertama
dibuat saat admin klik Simpan pertama kali).

## Admin page

`App\Filament\Pages\ReceiptSettings` — pola sama persis
`App\Filament\Pages\PaymentMethodSettings` (statePath `data`, `mount()` fill
dari `ReceiptSetting::current()`, `content()` pakai `Form`+`EmbeddedSchema`+
`Actions` submit ke `save()`).

Sections:
- **Info Toko**: TextInput `store_name` (required), `address`, `phone`,
  `website`.
- **Logo**: `FileUpload` `logo_path`, image, disk `public`, directory
  `receipts/logo`.
- **Footer**: `Textarea` `footer_text`, `maxLength(500)`, helper text
  jelasin tiap baris baru jadi baris terpisah di struk.
- **QR Code**: `Toggle` `show_qr` label "Tampilkan QR e-receipt".

`save()`: `ReceiptSetting::query()->first()?->update($data) ??
ReceiptSetting::create($data)` — upsert baris tunggal.

## Receipt blade changes

`App\Livewire\Pos\Receipt` — tambah `#[Computed] receiptSettings()` →
`ReceiptSetting::current()`.

`receipt.blade.php`:
- Header: `{{ $this->receiptSettings->store_name }}` dst menggantikan 4 baris
  hardcoded. Logo (`<img>`) tampil di atas nama toko kalau `logo_path` ada.
- Footer: loop tiap baris dari `$this->receiptSettings->footer_text` (split
  by newline) jadi `<p>` masing-masing; kalau kosong, tidak render section
  footer sama sekali.
- QR block: wrap dengan `@if($this->receiptSettings->show_qr)`.
- PPN row: label jadi
  `PPN ({{ $this->subtotal > 0 ? round($this->order->tax / $this->subtotal * 100) : 0 }}%)`
  menggantikan string `"PPN (11%)"` hardcoded.

## Testing

Tidak ada test otomatis baru — settings page + conditional blade render
sama proporsinya dengan `PaymentMethodSettings` yang juga tanpa test
(precedent di spec QRIS). Verifikasi manual via `/run`: buka halaman
settings, ubah nilai, cek struk cetak reflect perubahan.

# Commit

Use /commit-work skills, commit tanpa co-author dan hanya oneline, sesuaikan per konteks perubahan