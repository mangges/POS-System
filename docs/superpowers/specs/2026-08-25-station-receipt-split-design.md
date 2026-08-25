# Station Receipt Split — Design

## Problem

Satu struk thermal saat ini mencakup semua item order (harga, subtotal, total).
Kasir/bar/kitchen butuh tiket kerja terpisah: bar dan kitchen cuma perlu tahu
item apa yang harus disiapkan, bukan harga. Infrastruktur routing item per
station sudah ada (`products.destination`: `kitchen` | `bar` | `cashier`,
lihat [products migration](../../../database/migrations/2026_06_02_133642_create_products_table.php))
tapi belum dipakai di alur cetak struk.

## Scope

- Halaman struk kasir: `/cashier/receipt/{orderId}` ([PosReceipt.php](../../../app/Filament/CustomPages/PosReceipt.php) → [Receipt.php](../../../app/Livewire/Pos/Receipt.php)).
- Halaman e-receipt customer `/{token}` (hasil scan QR selesai bayar) **tidak** ikut berubah — tetap struk tunggal seperti sekarang.

## Data Model

Tidak ada migration baru. Destination item order diturunkan dari
`OrderItem::product->destination` saat render, bukan disimpan snapshot di
`order_items`. Ini konsisten dengan pola yang sudah dipakai di
[ProductForm.php](../../../app/Filament/Resources/Products/Schemas/ProductForm.php)
dan seeder produk.

Kategorisasi:
- `destination = kitchen` → tiket kitchen
- `destination = bar` → tiket bar
- `destination = cashier` → cuma di struk kasir utama, tidak muncul di tiket bar/kitchen

## Components

### 1. `Receipt.php` — computed baru

```php
#[Computed]
public function barItems()
{
    return $this->orderItems->filter(
        fn (OrderItem $item) => $item->product->destination === 'bar'
    );
}

#[Computed]
public function kitchenItems()
{
    return $this->orderItems->filter(
        fn (OrderItem $item) => $item->product->destination === 'kitchen'
    );
}
```

`orderItems` sudah eager-load `items.product` lewat `order()` computed —
tidak ada N+1 baru.

### 2. Partial baru: `_station_ticket.blade.php`

Reuse class CSS thermal existing (`.thermal-receipt`, `.receipt-header`,
`.receipt-divider`, `.receipt-row`, `.receipt-item`, `.receipt-item-details`,
`.receipt-item-name`, `.receipt-item-qty`) supaya lebar kertas 300px dan font
monospace konsisten dengan struk kasir.

Parameter: `items` (Collection<OrderItem>), `stationLabel` (string, "BAR" /
"DAPUR"), `domId` (string).

Isi:
- Header: `stationLabel` besar (ganti posisi logo/nama toko), no. order
  (`order_number`), nama meja (`order.table.name`) atau nama customer, jam
  (`order.created_at->format('H:i')`).
- Divider.
- List item: nama produk + qty, baris kedua catatan (`item.notes`) kalau
  ada. **Tidak ada harga, subtotal, total, QR, footer.**

### 3. `receipt.blade.php` — branch `orderId` (kasir view)

Tambah setelah `_thermal` include di dalam `receipt-scroll-area`, sebagai
sibling tersembunyi (bukan di dalam `receipt-scroll-area`, supaya tidak ikut
scroll/preview):

```blade
<div id="ticket-cashier">
    @include('livewire.pos.receipts._thermal')
</div>

@if($this->barItems->isNotEmpty())
    <div id="ticket-bar" style="display:none">
        @include('livewire.pos.receipts._station_ticket', [
            'items' => $this->barItems,
            'stationLabel' => 'BAR',
        ])
    </div>
@endif

@if($this->kitchenItems->isNotEmpty())
    <div id="ticket-kitchen" style="display:none">
        @include('livewire.pos.receipts._station_ticket', [
            'items' => $this->kitchenItems,
            'stationLabel' => 'DAPUR',
        ])
    </div>
@endif
```

Branch `@else` (customer `/{token}`) tidak disentuh.

### 4. Tombol cetak

`onclick="window.print()"` → `onclick="printAllReceipts()"`.

```js
function printAllReceipts() {
    const ids = ['ticket-cashier', 'ticket-bar', 'ticket-kitchen']
        .filter(id => document.getElementById(id) !== null);
    let i = 0;

    function showOnly(activeId) {
        ids.forEach(id => {
            document.getElementById(id).style.display =
                id === activeId ? 'block' : 'none';
        });
    }

    function afterPrint() {
        i++;
        if (i < ids.length) {
            showOnly(ids[i]);
            window.print();
        } else {
            showOnly('ticket-cashier');
            window.removeEventListener('afterprint', afterPrint);
        }
    }

    window.addEventListener('afterprint', afterPrint);
    showOnly(ids[0]);
    window.print();
}
```

Script ditaruh di `receipt.blade.php` branch `orderId` saja (tidak perlu di
`_thermal.blade.php` supaya tidak kebawa ke halaman customer).

### 5. CSS

`#printable-receipt` di `_thermal.blade.php` tetap dalam `.receipt-container`
sehingga aturan `@media print` yang sudah ada (`body * { visibility:hidden }`,
`.receipt-container * { visibility:visible }`) tetap berlaku untuk
`ticket-cashier`. `ticket-bar`/`ticket-kitchen` diletakkan **di luar**
`.receipt-preview-wrapper` tapi masih di dalam `.receipt-container`, jadi ikut
`visibility:visible` juga — kemunculannya diatur oleh inline `style="display"`
yang di-toggle JS, bukan CSS print baru. Tidak perlu ubah `receipt.css`.

## Edge Cases

- Order isi kitchen doang / bar doang → tombol tetap 1 klik, cuma jalan 2x
  print (kasir + 1 station), bukan 3x.
- Order isi cashier-only (retail item, tanpa proses dapur) → cuma tiket
  kasir yang render, tidak ada `ticket-bar`/`ticket-kitchen` di DOM sama
  sekali.
- Produk lama tanpa `destination` eksplisit → default migration `kitchen`,
  otomatis masuk tiket kitchen, tidak perlu migration data.

## Testing

Tambah di [ReceiptTest.php](../../../tests/Feature/Livewire/Pos/ReceiptTest.php):
order dengan 3 produk (destination kitchen/bar/cashier) →
- struk kasir (`#printable-receipt`) tetap nampilin ketiga item + harga.
- `#ticket-bar` cuma nampilin nama item destination bar, tanpa harga.
- `#ticket-kitchen` cuma nampilin nama item destination kitchen, tanpa harga.
- order tanpa item bar → `#ticket-bar` tidak ada di response HTML.
