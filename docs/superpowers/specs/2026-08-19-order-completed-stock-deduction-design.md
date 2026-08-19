# Decrease Stock on Order Completed

## Problem

`StockMovement` sekarang cuma dipakai buat sisi "in" (restock, lewat relation
manager yang dibangun sebelumnya). Belum ada mekanisme yang menurunkan stok
saat order selesai — padahal itu fungsi utama `stock_movements`: tiap
transaksi yang mengurangi stok (jual produk) harus juga tercatat, bukan cuma
penambahan.

Order bisa jadi `Completed` lewat dua jalur: `OrderService::finalizeOrder()`
(alur normal kasir) dan edit manual field `status` di `OrderForm` (Filament
admin, `Select::make('status')` sudah ada). Mekanisme decrease harus
konsisten kepotong dari keduanya.

## Scope

- `OrderObserver` yang mendeteksi transisi status order jadi `Completed`,
  lalu membuat `StockMovement` tipe `out` untuk tiap item order — ke raw
  material (lewat `Recipe`) kalau produk `has_recipe = true`, atau langsung
  ke produk kalau `false`.
- Reuse `StockMovementObserver` yang sudah ada (dibangun sesi sebelumnya) —
  observer baru ini cuma insert `StockMovement` row, decrement `stock`
  kolomnya tetap otomatis dari observer lama, tidak ditulis ulang.

Out of scope (belum diminta):
- Validasi/peringatan kalau produk `has_recipe = true` tapi tidak punya
  `Recipe` row sama sekali (silently tidak memotong apa-apa).
- Mencegah stok jadi negatif.
- Reversal otomatis (mis. order dibatalkan setelah completed) — tidak ada
  status "completed → cancelled" yang valid sekarang (lihat `OrderStatus`),
  jadi tidak perlu ditangani.

## Design

`app/Observers/OrderObserver.php`, didaftarkan di
`AppServiceProvider::boot()` via `Order::observe(OrderObserver::class)`:

```php
public function updated(Order $order): void
{
    if (! $order->wasChanged('status') || $order->status !== OrderStatus::Completed) {
        return;
    }

    DB::transaction(function () use ($order) {
        foreach ($order->items()->with('product.recipes')->get() as $item) {
            $product = $item->product;

            if ($product->has_recipe) {
                foreach ($product->recipes as $recipe) {
                    StockMovement::create([
                        'reference_id' => $recipe->raw_material_id,
                        'reference_type' => 'raw_material',
                        'type' => 'out',
                        'quantity' => $recipe->quantity * $item->quantity,
                        'user_id' => $order->user_id,
                        'notes' => "Order {$order->order_number}",
                    ]);
                }
            } else {
                StockMovement::create([
                    'reference_id' => $product->id,
                    'reference_type' => 'product',
                    'type' => 'out',
                    'quantity' => $item->quantity,
                    'user_id' => $order->user_id,
                    'notes' => "Order {$order->order_number}",
                ]);
            }
        }
    });
}
```

`wasChanged('status')` guards against re-firing on subsequent unrelated
saves of an already-completed order (e.g. editing `notes` later).

## Testing

- `OrderObserver` test: completing an order with a `has_recipe = false`
  product decrements `products.stock` by the ordered quantity and creates
  one `out` `StockMovement`.
- Completing an order with a `has_recipe = true` product (with 2 `Recipe`
  ingredients) decrements both `raw_materials.stock` rows by
  `recipe.quantity * item.quantity` and creates one `StockMovement` per
  ingredient.
- Saving an already-`Completed` order again (status unchanged) does not
  create duplicate movements.
- Completing via direct `Order::update(['status' => ...])` (simulating the
  Filament manual-edit path) triggers the same deduction as
  `OrderService::finalizeOrder()`.
