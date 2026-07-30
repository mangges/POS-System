# Desain Database Notifikasi — POS System (Admin, Cashier, User)

> **Status:** ✅ Diimplementasikan — lihat [Implementasi](#implementasi) di bagian bawah untuk lokasi file (migration, model, enum).

## Konsep Dasar

Karena 3 role punya kebutuhan notifikasi yang beda-beda (tapi bisa saling overlap, misal "order baru" relevan untuk Admin *dan* Cashier), desain ini memisahkan:

1. **Isi notifikasi** (apa pesannya, tipe apa, terkait record apa) → tabel `app_notifications`
2. **Siapa yang menerima & status baca** → tabel `app_notification_recipients`

Cukup 2 tabel — jenis notifikasi (`type`) disimpan langsung sebagai kolom di `app_notifications`, tidak perlu tabel master terpisah.

Dengan begitu, satu event (misal "order #123 dibuat") bisa memicu satu baris `app_notifications`, tapi punya banyak baris `app_notification_recipients` (misal: broadcast ke semua Cashier + 1 Admin tertentu), masing-masing dengan status read/unread sendiri-sendiri.

> **Kenapa prefix `app_` dan bukan `notifications` polos?** `User` model sudah pakai trait `Notifiable`, dan Admin panel pakai Filament — keduanya punya fitur notifikasi native masing-masing yang juga memakai nama tabel `notifications` (dengan skema berbeda: `notifiable_type`/`notifiable_id`/`read_at`). Supaya tidak pernah ambigu/bentrok dengan konvensi Laravel/Filament, sistem notifikasi custom ini pakai prefix `app_`. Fitur notifikasi native Filament sengaja **tidak dipakai** sama sekali di project ini — Cashier dan Landing Page (customer) berjalan sebagai Livewire biasa di luar Filament, jadi supaya 1 sistem konsisten dipakai di semua role, Admin juga tetap pakai sistem custom ini (bell Admin nanti di-inject ke panel Filament lewat render hook, bukan lewat fitur notifikasi bawaan Filament).

---

## 1. `app_notifications` (Isi notifikasi)

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | BIGINT PK | |
| type | VARCHAR(50) | kode jenis notifikasi, contoh: `new_order`, `low_stock`, `order_ready`, `payment_received` |
| title | VARCHAR(150) | contoh: "Order Baru #INV-0234" |
| message | TEXT NULL | isi pesan lengkap |
| record_type | VARCHAR(50) NULL | alias entitas terkait: `order`, `product`, dst — lihat [morph map](#morph-map-alias) |
| record_id | BIGINT NULL | ID entitas terkait (order_id, product_id, dll) |
| priority | ENUM('low','medium','high','urgent') | default `medium` |
| data | JSON NULL | payload tambahan fleksibel (contoh: `{"order_total": 150000, "table_no": 4}`) — berguna untuk deep-link di frontend tanpa perlu join berat |
| created_by | BIGINT NULL FK → users.id, `nullOnDelete` | null jika dibuat sistem otomatis |
| created_at, updated_at | TIMESTAMP | |

Index: `(record_type, record_id)` dan `type`.

`record_type` + `record_id` = polymorphic relation Eloquent asli (`AppNotification::record()`, method `morphTo()`), bukan sekadar deskripsi teks — supaya tabel ini tidak perlu FK ke banyak tabel berbeda (order, product, dll) sekaligus, tapi tetap bisa langsung di-resolve ke model terkait lewat relasi.

Karena tidak ada tabel master, kolom `type` cukup distandarkan sebagai konstanta/enum di level kode aplikasi (bukan di database) — **belum dibuat** di iterasi ini karena daftar type per-role masih bisa berubah saat event/trigger dibangun. Berikut daftar `type` yang disarankan sebagai acuan awal per role:

**Admin**
- `new_order` — Order baru masuk
- `low_stock` — Stok produk menipis/habis
- `refund_request` — Permintaan refund/void
- `daily_sales_report` — Ringkasan penjualan harian
- `staff_activity` — Login mencurigakan / perubahan settingan penting
- `cashier_shift_closed` — Kasir menutup shift (untuk rekonsiliasi)

**Cashier**
- `new_order` — Order baru dari landing page perlu diproses
- `order_ready_to_serve` — Pesanan siap diantar/diambil
- `payment_received` — Pembayaran online dikonfirmasi
- `table_call` — Panggilan bantuan dari meja/customer
- `shift_reminder` — Reminder buka/tutup shift

**User (customer)**
- `order_confirmed` — Order diterima
- `order_processing` — Order sedang disiapkan
- `order_ready` — Order siap diambil/diantar
- `order_completed` — Order selesai
- `promo` — Info promo/diskon

> Karena satu `type` bisa relevan untuk lebih dari 1 role (contoh `new_order` untuk Admin & Cashier), role targeting **tidak** disimpan di sini, tapi ditentukan saat notifikasi dikirim (lihat tabel `app_notification_recipients`).

**`priority`** di-cast ke PHP enum `App\Enum\Notifications\NotificationPriority` (`Low`/`Medium`/`High`/`Urgent`, masing-masing punya `label()` dan `color()` untuk kebutuhan UI badge) — pola yang sama seperti `App\Enum\Orders\OrderStatus`.

---

## 2. `app_notification_recipients` (Target & status baca)

Ini tabel paling penting untuk membedakan kebutuhan tiap role — satu notifikasi bisa broadcast ke role, atau personal ke satu penerima.

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | BIGINT PK | |
| notification_id | BIGINT FK → app_notifications.id, `cascadeOnDelete` | |
| recipient_scope | ENUM('role','individual') default `individual` | apakah target-nya seluruh role atau satu penerima spesifik |
| recipient_role | ENUM('admin','cashier') NULL | diisi jika scope = `role` |
| recipient_type, recipient_id | polymorphic (`nullableMorphs`) | diisi jika scope = `individual` — lihat di bawah |
| is_read | BOOLEAN default false | |
| read_at | TIMESTAMP NULL | |
| is_archived | BOOLEAN default false | biar bisa "hide" tanpa hapus data |
| created_at, updated_at | TIMESTAMP | |

Index: `(recipient_type, recipient_id)` (otomatis dari `nullableMorphs`), `(recipient_role, is_read)`.

### Penyesuaian penting dari desain awal: siapa penerima personal?

Desain awal mengasumsikan penerima personal selalu punya `user_id` tetap (termasuk customer). Di codebase ini ternyata **customer di landing page adalah guest tanpa akun** — akses order dilakukan lewat kolom `orders.token` (`customers.id` sering `null`, lihat migration `add_customer_name_on_order` & `add_token_on_orders`). Karena itu:

- **`recipient_role`** hanya `enum('admin','cashier')` — customer tidak pernah jadi target broadcast by-role (tidak ada konsep "semua customer"), notifikasi customer selalu personal per-order.
- **Recipient personal** memakai polymorphic (`recipient_type` + `recipient_id`) alih-alih kolom `user_id` tunggal, supaya bisa menunjuk ke dua jenis entitas berbeda:
  - `App\Models\User` — untuk Admin & Cashier (staff, per-user read tracking).
  - `App\Models\Order` — untuk customer, di-anchor ke **Order** (bukan `Customer`) karena akses guest selalu lewat token order, bukan akun customer.

👉 Rekomendasi tetap sama seperti desain awal: untuk **Admin & Cashier** (internal staff, biasanya sedikit), pakai per-individu tracking (`scope='individual'`) supaya read-status akurat per orang. Untuk **User/customer**, notifikasi memang selalu personal (order milik dia sendiri), jadi otomatis `scope='individual'` dengan `recipient_type = Order`.

### Morph map alias

`recipient_type` (di `app_notification_recipients`) dan `record_type` (di `app_notifications`) sama-sama polymorphic Eloquent, tapi supaya nilainya tetap pendek & human-readable (bukan `App\Models\Order` penuh), didaftarkan **morph map** global di `App\Providers\AppServiceProvider::boot()`:

```php
Relation::morphMap([
    'order' => Order::class,
    'product' => Product::class,
    'user' => User::class,
]);
```

Jadi di database, `record_type`/`recipient_type` cukup berisi `'order'`, `'product'`, atau `'user'` — bukan nama class lengkap.

---

## Relasi Ringkas (ERD teks)

```
app_notifications (1) ── (N) app_notification_recipients
                                    │
                    recipient_role (scope=role)
                    atau
                    recipient_type + recipient_id (scope=individual)
                    → App\Models\User (admin/cashier)
                    → App\Models\Order (customer, via token)
```

---

## Contoh Query (Eloquent)

**Ambil semua notifikasi belum dibaca untuk staff tertentu:**
```php
$staff->notificationRecipients()->unread()->with('notification')->latest()->get();
```

**Broadcast notifikasi "order baru" ke semua Cashier aktif:**
```php
$notification = AppNotification::create([
    'type' => 'new_order',
    'title' => 'Order Baru #INV-0234',
    'message' => 'Ada pesanan baru dari meja 4',
    'record_type' => 'order',
    'record_id' => $order->id,
    'priority' => 'high',
]);

User::where('role', 'cashier')->get()->each(
    fn (User $cashier) => $cashier->notificationRecipients()->create([
        'notification_id' => $notification->id,
        'recipient_scope' => 'individual',
    ])
);
```

**Notifikasi status order untuk customer (guest, via order/token):**
```php
$notification = AppNotification::create([
    'type' => 'order_ready',
    'title' => 'Pesanan Siap Diambil',
    'message' => "Pesanan #{$order->order_number} kamu sudah siap!",
    'record_type' => 'order',
    'record_id' => $order->id,
    'priority' => 'medium',
]);

$order->notificationRecipients()->create([
    'notification_id' => $notification->id,
    'recipient_scope' => 'individual',
]);
```

`recipient_type` di-set otomatis oleh Eloquent lewat relasi `notificationRecipients()` (morph map `'order'`/`'user'`), tidak perlu di-set manual.

---

## Catatan Desain

- **`data` JSON** sengaja dibuat fleksibel supaya frontend bisa langsung render tanpa query tambahan (contoh: tampilkan `table_no`, `order_total` langsung di notif tanpa join ke tabel order).
- Kalau volume notifikasi besar (ribuan/hari), pertimbangkan **partitioning by created_at** atau job pembersihan (soft delete/archive notifikasi lama > 30 hari) supaya tabel `app_notification_recipients` tidak membengkak.
- Untuk **real-time delivery** (push/websocket), tabel ini adalah "source of truth"-nya saja — proses pengiriman real-time biasanya lewat event/queue (misal Laravel Events + Pusher/WebSocket) yang trigger setelah insert ke tabel ini. **Belum diimplementasikan** di iterasi ini.
- Karena `type` sekarang cuma kolom VARCHAR (bukan FK ke tabel master), validasi nilai yang diizinkan sebaiknya dilakukan di level aplikasi (enum/constant), bukan di database.

---

## Implementasi

| Bagian | File |
|---|---|
| Migration `app_notifications` | `database/migrations/2026_07_30_090000_create_app_notifications_table.php` |
| Migration `app_notification_recipients` | `database/migrations/2026_07_30_090001_create_app_notification_recipients_table.php` |
| Model | `app/Models/AppNotification.php`, `app/Models/AppNotificationRecipient.php` |
| Enum priority | `app/Enum/Notifications/NotificationPriority.php` |
| Morph map | `app/Providers/AppServiceProvider.php` (`boot()`) |
| Relasi tambahan | `User::notificationRecipients()`, `Order::notificationRecipients()` |

**Belum dibuat** (iterasi berikutnya): Livewire component bell/panel, event + broadcasting realtime, service/helper untuk create notification per trigger (`new_order`, `low_stock`, dll), route `/notifications`, konstanta/enum untuk `type`.
