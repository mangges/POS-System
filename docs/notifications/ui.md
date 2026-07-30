# UI: Fitur Notifikasi — POS System

**Status:** Draft
**Terakhir diupdate:** 2026-07-30
**Terkait dokumen:** [`prd.md`](./prd.md) · [`db.md`](./db.md) 

---

## 1. Komponen Utama

Notifikasi tampil dalam 2 bentuk komponen di semua role:

1. **Notification Bell (icon di navbar/header)** — indikator jumlah unread + trigger buka dropdown/panel.
2. **Notification Panel/Dropdown** — daftar notifikasi, bisa di-scroll, dengan aksi per item.

Untuk User (customer/landing page), ada tambahan:

3. **Order Status Tracker** — tampilan status order secara visual (bukan cuma list notifikasi), karena customer biasanya fokus ke satu order yang sedang berjalan.

---

## 2. Notification Bell

**Lokasi:** pojok kanan atas navbar, di semua halaman yang relevan per role.

**Elemen:**
- Icon lonceng
- Badge angka unread count (contoh: merah, muncul hanya jika > 0)
- Badge menghilang otomatis kalau unread count = 0

**Perilaku:**
- Klik → toggle buka/tutup Notification Panel
- Unread count di-update:
  - Saat pertama halaman dimuat (initial load)
  - Secara berkala (polling, misal tiap 10–15 detik) — atau real-time kalau nanti pakai broadcasting
  - Langsung berkurang saat user menandai notifikasi sebagai dibaca

**Prioritas visual:**
- Jika ada notifikasi `priority = high` atau `urgent` yang belum dibaca, badge bisa diberi animasi (pulse) atau warna berbeda dari notifikasi biasa — supaya Cashier/Admin tidak melewatkan hal urgent (misal: table call, stok habis).

---

## 3. Notification Panel (Dropdown/Sidebar)

**Struktur per item notifikasi:**

```
┌─────────────────────────────────────────┐
│ ● [icon]  Order Baru #INV-0234           │  ← title, bold jika unread
│           Ada pesanan baru dari meja 4   │  ← message, 1-2 baris, truncate
│           2 menit lalu                   │  ← relative time
└─────────────────────────────────────────┘
```

- **Titik/dot indikator** di kiri — tampil jika unread, hilang setelah dibaca
- **Icon** — sesuai `type` notifikasi (order, stok, refund, dll), mapping icon dilakukan di frontend berdasarkan kolom `type`
- **Title & message** — dari kolom `app_notifications.title` / `app_notifications.message`
- **Waktu relatif** — dihitung dari `app_notification_recipients.created_at` ("2 menit lalu", "kemarin", dst)
- **Klik item** → deep-link ke halaman terkait (pakai `record_type` + `record_id`) sekaligus otomatis menandai dibaca

**Header panel:**
- Judul "Notifikasi"
- Tombol "Tandai semua dibaca" (hanya aktif kalau ada yang unread)
- (Opsional) Tab filter: "Semua" / "Belum dibaca"

**Empty state:**
- Ilustrasi/icon sederhana + teks "Belum ada notifikasi" saat list kosong

**Footer (opsional, jika daftar panjang):**
- Link "Lihat semua notifikasi" → ke halaman penuh `/notifications` (list lengkap dengan pagination), karena dropdown biasanya cuma tampilkan 5–10 terbaru

---

## 4. Perilaku Spesifik per Role

### Admin
- Notification Bell muncul di layout admin (`layouts/admin.blade.php` atau sejenis)
- Item dengan `priority = high/urgent` (stok habis, refund, staff activity) ditampilkan lebih menonjol — bisa pakai warna border kiri berbeda (merah untuk urgent, oranye untuk high)
- Klik notifikasi `low_stock` → deep-link ke halaman detail produk
- Klik notifikasi `new_order` → deep-link ke halaman detail order
- Klik notifikasi `refund_request` → deep-link ke halaman approval refund

### Cashier
- Notification Bell muncul di layout cashier/kasir (biasanya di halaman POS utama)
- **Notifikasi `table_call` dan `order_ready_to_serve`** perlu perhatian ekstra karena real-time-sensitive — pertimbangkan toast/pop-up sementara di pojok layar (auto-dismiss setelah beberapa detik) selain masuk ke panel, supaya Cashier yang sedang sibuk di layar POS tetap notice tanpa harus buka dropdown
- Klik notifikasi `new_order` → langsung ke halaman proses order tersebut

### User (Customer — landing page)
- Notification Bell tetap ada, tapi **Order Status Tracker** jadi elemen utama saat customer baru saja checkout — tampil sebagai stepper/progress bar, bukan cuma list:

```
[✓ Diterima] → [● Diproses] → [○ Siap] → [○ Selesai]
```

- Status tracker ini update otomatis (polling ringan) selama customer masih di halaman order tersebut, tanpa perlu buka notification panel
- Notifikasi promo (`type = promo`) masuk ke panel biasa, prioritas rendah, tidak perlu toast/pop-up

---

## 5. Implementasi Teknis (Blade)

Karena stack pakai Blade, komponen ini sebaiknya dipecah jadi **Blade component** yang reusable across role, dengan sedikit perbedaan konten berdasarkan role:

```
resources/views/components/notification/
├── bell.blade.php          ← icon + badge, terima prop unread_count
├── panel.blade.php         ← dropdown list, terima prop notifications
├── item.blade.php          ← 1 baris notifikasi, terima prop notification
└── empty-state.blade.php
```

**Pemanggilan di layout:**
```blade
<x-notification.bell :unread-count="$unreadCount" />
```

**Update data (unread count & list) tanpa reload:**
Karena Blade render server-side, bagian dinamis ini butuh sedikit JS:
- Polling via `fetch()` ke endpoint notifikasi (lihat `api.md`, menyusul) setiap interval tertentu, lalu re-render list & badge di client-side
- Alternatif lebih ringan: pakai **Livewire component** untuk `bell` dan `panel`, supaya polling dan update otomatis ditangani Livewire tanpa perlu JS/endpoint JSON manual (`wire:poll`)

> Keputusan Alpine.js/vanilla JS vs Livewire akan menentukan detail teknis di api — didiskusikan saat diperlukan.

---

## 6. States yang Perlu Ditangani di UI

| State | Tampilan |
|---|---|
| Loading (pertama buka panel) | Skeleton loader / spinner kecil |
| Kosong (tidak ada notifikasi) | Empty state dengan ilustrasi + teks |
| Ada unread | Badge count di bell, dot indikator per item |
| Semua sudah dibaca | Badge hilang, tidak ada dot |
| Gagal fetch (network error) | Pesan error kecil di panel, tombol retry |
| Notifikasi urgent baru masuk (Cashier) | Toast/pop-up sementara, terpisah dari panel |

---

## 7. Open Questions

- Apakah toast/pop-up untuk notifikasi urgent (table call, order ready) dibutuhkan di semua role, atau khusus Cashier saja?
- Apakah halaman "Lihat semua notifikasi" (`/notifications`) dibutuhkan di v1, atau dropdown 10 item terakhir sudah cukup?
- Pakai Alpine.js/vanilla JS + polling manual, atau Livewire untuk komponen ini?
