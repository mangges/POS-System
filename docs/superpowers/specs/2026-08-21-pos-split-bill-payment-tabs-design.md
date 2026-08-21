# Split Bill Payment Tabs (POS)

## Problem

Split bill di POS (`Cashier::checkoutSplit()`, `app/Livewire/Pos/Cashier.php:318`)
saat ini membuat N `Order` (status `Pending`) — satu per grup split — lalu
redirect dengan pesan "Bayar tiap pesanan dari menu Draft." Cashier harus
keluar dari flow checkout, buka modal Drafts, cari tiap order satu-satu, baru
bisa bayar. Tidak ada jalur langsung dari "checkout split" ke pembayaran.

Yang diinginkan: setelah checkout split, payment modal yang sudah ada
langsung terbuka, dengan tab (gaya tab browser) di bagian atas — satu tab per
grup/orang — supaya cashier bisa bayar tiap orang tanpa pernah meninggalkan
modal.

Scope: POS cashier page saja (`Cashier.php` + `payment_modal.blade.php`).
Self-order/guest flow (`LandingPage.php`,
`landing-page/split_bill_panel.blade.php`) tidak disentuh — arsitektur
pembayarannya beda (guest tidak "bayar" per tab, langsung submit).

## Scope

- `checkoutSplit()` tetap membuat N `Order`+`Payment` (`status=Pending`)
  seperti sekarang — ini yang membuat split yang ditinggal cashier
  tetap aman muncul di Drafts (invarian dari commit `af8c6ac`, jangan
  dirusak).
- Setelah order dibuat, payment modal langsung terbuka (bukan redirect ke
  Drafts) dengan tab per grup.
- Klik tab pindah konteks pembayaran (order id, metode bayar, uang
  diterima) ke grup itu tanpa reload halaman.
- Tab yang sudah lunas ditandai centang, tetap bisa diklik untuk dilihat
  tapi read-only (tidak bisa dibayar ulang).
- Setelah tab aktif lunas, otomatis pindah ke tab belum-lunas berikutnya;
  modal tetap terbuka.
- Setelah semua tab lunas: modal ditutup, cart/split state direset,
  redirect ke halaman struk order terakhir (satu-satunya titik redirect
  penuh — aman karena tidak ada tab lain yang masih perlu state Livewire).

Out of scope (belum diminta):
- Struk per tab (cetak tiap kali satu orang selesai bayar) — struk cuma
  muncul di akhir, untuk order terakhir yang lunas.
- Self-order/guest split payment flow — tetap seperti sekarang (submit
  langsung, tanpa tab bayar).
- Kolom/migration baru — grup split tetap Order biasa (`customer_name` =
  nama grup), tidak ada entity split baru.

## Design

### 1. State baru di `Cashier.php`

```php
public ?int $activeSplitIndex = null; // null = bukan lagi di split-payment mode
```

`$splitGroups` (dari `CartCalculation` trait) dapat dua key tambahan per
elemen, diisi saat checkout: `order_id` (int, id `Order` yang baru dibuat)
dan `paid` (bool, default `false`).

### 2. `checkoutSplit()` — buka modal, jangan redirect

Validasi dan transaction loop tetap sama (`canCheckoutSplit()`, guard
`deleteDraft($currentOrderId)` untuk draft yang lagi kebuka, `DB::transaction`
loop `processOrder()` per grup). Bedanya:

- Simpan `order_id` hasil `processOrder()` balik ke
  `$this->splitGroups[$index]['order_id']`, sekaligus set
  `$this->splitGroups[$index]['paid'] = false` di baris yang sama (kedua key
  ini yang dipakai tab bar dan blade nanti — belum ada di `addSplitGroup()`
  sebelumnya, jadi baru muncul persis di titik ini).
- **Jangan** reset `cart`/`splitGroups`/`splitMode` — masih dipakai untuk
  render tab bar dan hitung subtotal per grup (`splitGroupSubtotal()` baca
  dari `$this->cart` + `assignments`, bukan dari DB).
- Set `$this->activeSplitIndex = 0`, `$this->currentOrderId =
  $splitGroups[0]['order_id']`, `$this->showPaymentModal = true`,
  panggil `ensureActivePaymentMethod()`.
- Hapus `redirect()->with('message', ...)` — tidak ada lagi redirect di sini.

### 3. `switchSplitTab(int $index)` — method baru

```php
public function switchSplitTab(int $index): void
{
    if (! isset($this->splitGroups[$index]['order_id'])) {
        return;
    }

    $this->activeSplitIndex = $index;
    $this->currentOrderId = $this->splitGroups[$index]['order_id'];
    $this->paymentMethod = 'cash';
    $this->paymentConfirmed = false;
    $this->cashReceived = null;
    $this->ensureActivePaymentMethod();
}
```

Dipanggil dari klik tab (termasuk tab yang sudah lunas, sekadar untuk
melihat — tombol "Selesai & Cetak Struk" di blade tetap disabled untuk tab
`paid`).

### 4. `finalizeOrder()` — cabang split vs normal

Bagian validasi + `orderService->finalizeOrder($currentOrderId, ...)` tetap
sama persis. Setelah itu:

```php
if ($this->activeSplitIndex === null) {
    // path normal, tidak berubah
    $this->resetCashier();
    $this->closePaymentModal();
    $this->showReceipt($order->id);
    return;
}

$this->splitGroups[$this->activeSplitIndex]['paid'] = true;

$nextUnpaid = collect($this->splitGroups)
    ->search(fn ($g) => ! $g['paid']);

if ($nextUnpaid !== false) {
    $this->switchSplitTab($nextUnpaid);
    return;
}

$this->resetCashier();
$this->reset(['splitGroups', 'splitMode', 'activeSplitIndex']);
$this->closePaymentModal();
$this->showReceipt($order->id);
```

### 5. `payment_modal.blade.php` — tab bar + angka per grup

Tab bar baru di atas modal, dirender hanya kalau `count($splitGroups) > 1`:

```blade
@if(count($splitGroups) > 1)
    <div class="payment-split-tabs">
        @foreach($splitGroups as $index => $group)
            <button type="button"
                class="payment-split-tab {{ $activeSplitIndex === $index ? 'active' : '' }} {{ $group['paid'] ? 'is-paid' : '' }}"
                wire:click="switchSplitTab({{ $index }})">
                @if($group['paid'])<i class="bi bi-check-circle-fill"></i>@endif
                {{ $group['name'] }}
            </button>
        @endforeach
    </div>
@endif
```

Semua tempat modal baca `$this->subtotal` / `$this->taxAmount` /
`$this->total` / `$this->change` / `$this->customerName` diganti pakai
angka grup aktif ketika `$activeSplitIndex !== null`. Cara paling ringan:
dua method kecil di `Cashier.php` (mirror `taxAmount()`/`total()` yang
sudah ada di `CartCalculation`, tinggal dikasih subtotal grup):

```php
public function splitGroupTax(int $index): float
{
    return $this->cartCalculatorService->tax($this->splitGroupSubtotal($index));
}

public function splitGroupTotal(int $index): float
{
    return $this->cartCalculatorService->total(
        $this->splitGroupSubtotal($index),
        $this->splitGroupTax($index)
    );
}
```

Di blade, di baris paling atas partial:

```php
@php
    $displaySubtotal = $activeSplitIndex !== null ? $this->splitGroupSubtotal($activeSplitIndex) : $this->subtotal;
    $displayTax = $activeSplitIndex !== null ? $this->splitGroupTax($activeSplitIndex) : $this->taxAmount;
    $displayTotal = $activeSplitIndex !== null ? $this->splitGroupTotal($activeSplitIndex) : $this->total;
    $displayName = $activeSplitIndex !== null ? $splitGroups[$activeSplitIndex]['name'] : $this->customerName;
    $displayChange = $cashReceived !== null ? max(0, $cashReceived - $displayTotal) : null;
@endphp
```

lalu semua `$this->subtotal`/`$this->taxAmount`/`$this->total`/
`$this->change`/`$this->customerName` yang ada sekarang diganti ke
`$displaySubtotal`/`$displayTax`/`$displayTotal`/`$displayChange`/
`$displayName` (termasuk `$isInsufficient`/`$canFinalize` yang sekarang
pakai `$this->total`). Path normal (non-split) hasilnya identik dengan
sekarang karena tinggal fallback ke `$this->subtotal` dkk.

Tombol submit (`finalizeOrder`) ditambah guard: disabled juga kalau
`$activeSplitIndex !== null && $splitGroups[$activeSplitIndex]['paid']`
(tidak bisa bayar ulang tab yang sudah lunas).

### 6. QRIS amount ikut per-grup

`qrisImage()` (`PaymentMethodSelection` trait) dan `openQrisPreviewModal()`
(`Cashier.php:180`) sekarang sama-sama pakai `$this->total` (total cart
penuh) — kalau tidak disesuaikan, QR yang tampil saat bayar salah satu tab
split akan menagih total seluruh cart, bukan total grup itu saja.

- Di `payment_modal.blade.php`, ganti pemakaian `$this->qrisImage` jadi
  `$this->qrisImageForAmount($displayTotal)` (helper ini sudah ada di
  `PaymentMethodSelection`, tidak perlu kode backend baru).
- `openQrisPreviewModal()` diubah supaya eksplisit set
  `$this->previewQrisAmount` sebelum buka modal, bukan mengandalkan
  fallback `?? $this->total` di `qris_preview_modal.blade.php`:

```php
public function openQrisPreviewModal()
{
    $this->previewQrisAmount = (int) round(
        $this->activeSplitIndex !== null
            ? $this->splitGroupTotal($this->activeSplitIndex)
            : $this->total
    );
    $this->showQrisPreviewModal = true;
}
```

Path normal (non-split) hasilnya sama seperti sekarang (`$this->total`),
jadi tidak ada regresi.

### 7. Safety net — tidak berubah

Kalau cashier menutup modal sebelum semua tab lunas, order yang belum
`paid` tetap `status=Pending` di DB — otomatis muncul di Drafts modal
seperti sekarang (query `draftOrders()` tidak disentuh), dan tetap bisa
dibayar lewat `finalizeTableOrder()` yang sudah ada. Tidak ada perubahan
di jalur itu.

## Testing

- `CashierSplitBillTest`: `checkoutSplit()` membuka `showPaymentModal`
  (bukan redirect lagi), `activeSplitIndex === 0`, `currentOrderId` sama
  dengan order grup pertama, `splitGroups.*.order_id` terisi.
- `switchSplitTab()`: pindah `currentOrderId`/`activeSplitIndex`, reset
  `paymentMethod`/`cashReceived`/`paymentConfirmed`; index invalid/tanpa
  `order_id` no-op.
- `finalizeOrder()` di split mode: order aktif jadi `Completed`, tab
  ditandai `paid`, `activeSplitIndex` pindah ke tab belum lunas
  berikutnya, modal masih terbuka (`showPaymentModal` tetap `true`).
- `finalizeOrder()` pada tab terakhir: semua order `Completed`,
  `splitGroups`/`splitMode`/`activeSplitIndex` ke-reset, modal tertutup.
- `finalizeOrder()` path normal (non-split, `activeSplitIndex === null`):
  perilaku tidak berubah dari sekarang (regression check).
- Split ditinggal separuh jalan (1 dari 2 tab lunas): order kedua tetap
  `Pending` dan tetap muncul di `draftOrders()`.
- `openQrisPreviewModal()` di split mode: `previewQrisAmount` sama dengan
  `splitGroupTotal($activeSplitIndex)`, bukan total cart penuh.
