# PRD: Fitur Notifikasi — POS System

**Status:** Draft
**Terakhir diupdate:** 2026-07-30
**Terkait dokumen:** [`db.md`](./db.md) · [`ui.md`](./ui.md)

---

## 1. Problem

Saat ini POS system punya 3 jenis role (Admin, Cashier, User/customer) yang bekerja secara terpisah tanpa mekanisme pemberitahuan real-time. Akibatnya:

- **Cashier** tidak langsung tahu ada order baru masuk dari landing page, sehingga order bisa telat diproses.
- **Admin** tidak punya visibilitas real-time terhadap kondisi operasional (stok menipis, permintaan refund, aktivitas mencurigakan) — semua info baru diketahui saat cek manual.
- **User/customer** tidak tahu status pesanannya (diproses, siap, selesai) tanpa harus refresh/cek manual ke landing page.

Tanpa notifikasi, operasional jadi reaktif (nunggu dicek) bukan proaktif (langsung diberi tahu), yang berdampak ke kecepatan layanan dan pengalaman customer.

---

## 2. Goals

- Setiap role menerima informasi yang **relevan dengan tugasnya** secara real-time (atau near real-time), tanpa noise dari info yang tidak relevan buat role tersebut.
- Cashier bisa memproses order lebih cepat karena langsung diberi tahu saat order masuk.
- Admin punya visibilitas operasional (stok, refund, shift, sales) tanpa harus cek manual berkala.
- Customer merasa "terinformasi" soal status pesanannya, mengurangi pertanyaan berulang ("pesanan saya sampai mana?").
- Sistem notifikasi cukup generic/scalable untuk menambah jenis notifikasi baru di masa depan tanpa perlu restrukturisasi database.

### Non-goals (di luar scope versi ini)
- Notifikasi via WhatsApp/SMS/email (bisa jadi fase berikutnya).
- Notifikasi marketing otomatis berbasis segmentasi customer (di luar `promo` sederhana).
- Analytics/reporting dari data notifikasi (open rate, dsb).

---

## 3. User Roles & Kebutuhan

### Admin
Butuh visibilitas operasional & pengambilan keputusan.

| Notifikasi | Trigger | Prioritas |
|---|---|---|
| Order baru masuk | Customer/landing page membuat order | Medium |
| Stok produk menipis/habis | Stok produk di bawah threshold | High |
| Permintaan refund/void | Cashier/customer mengajukan refund | High |
| Ringkasan penjualan harian | Scheduled (misal akhir hari) | Low |
| Aktivitas staff mencurigakan | Login gagal berulang, perubahan setting sensitif | High |
| Cashier menutup shift | Cashier melakukan close shift | Low |

### Cashier
Butuh info operasional harian untuk memproses transaksi.

| Notifikasi | Trigger | Prioritas |
|---|---|---|
| Order baru perlu diproses | Customer membuat order dari landing page | High |
| Pesanan siap diantar/diambil | Dapur/kitchen menandai order selesai disiapkan | High |
| Pembayaran diterima (online) | Payment gateway konfirmasi pembayaran | Medium |
| Panggilan bantuan dari meja/customer | Customer memicu "call waiter" | High |
| Reminder buka/tutup shift | Scheduled berdasarkan jadwal shift | Low |

### User (Customer — landing page)
Butuh update status pesanan sendiri, personal (bukan broadcast).

| Notifikasi | Trigger | Prioritas |
|---|---|---|
| Order diterima/dikonfirmasi | Cashier/sistem konfirmasi order | Medium |
| Order sedang diproses | Status order diupdate ke "processing" | Medium |
| Order siap diambil/diantar | Status order diupdate ke "ready" | High |
| Order selesai | Status order diupdate ke "completed" | Low |
| Info promo/diskon | Admin membuat campaign promo | Low |

---

## 4. Functional Requirements

1. Sistem dapat mengirim satu notifikasi ke **banyak penerima sekaligus** (broadcast ke role) maupun ke **satu user spesifik** (personal).
2. Setiap penerima punya status baca (**read/unread**) yang independen satu sama lain.
3. Notifikasi dapat dikaitkan ke record lain di sistem (order, produk, shift, refund) untuk keperluan **deep-link** — klik notifikasi langsung membawa ke halaman terkait.
4. Notifikasi punya level **prioritas** (low/medium/high/urgent) yang memengaruhi tampilan (misal: high/urgent tampil lebih menonjol atau disertai sound/badge).
5. User dapat **menandai notifikasi sebagai sudah dibaca**, baik satu per satu maupun "tandai semua sebagai dibaca".
6. User dapat **mengarsipkan/menyembunyikan** notifikasi tanpa menghapus data dari sistem.
7. Setiap role hanya melihat notifikasi yang relevan dengan role-nya (Admin tidak lihat notif personal customer, Customer tidak lihat notif internal staff).
8. (Opsional/fase 2) User dapat mengatur preferensi channel notifikasi per jenis (in-app saja / + push / + email).

---

## 5. Non-Functional Requirements

- **Real-time / near real-time**: notifikasi kritikal (order baru, table call) idealnya tampil dalam hitungan detik, bukan perlu refresh manual.
- **Scalable**: skema data harus mendukung penambahan jenis notifikasi baru tanpa migrasi struktural (lihat `db.md` — `type` sebagai kolom fleksibel, bukan tabel master).
- **Tidak boleh mengganggu performa transaksi utama** — pengiriman notifikasi (terutama broadcast) tidak boleh memblokir proses order/payment.
- **Data notifikasi lama** perlu strategi retensi (arsip/soft delete) agar tabel tidak membengkak seiring waktu.

---

## 6. Scope

**In-scope (v1):**
- In-app notification (bell icon / notification center) untuk ketiga role.
- Read/unread tracking per user.
- Deep-link ke record terkait (order, produk, dll).
- Broadcast ke role & personal ke user.

**Out-of-scope (v1, kandidat fase berikutnya):**
- Push notification (mobile/browser).
- Notifikasi via email/WhatsApp.
- Preferensi notifikasi granular per user.
- Analytics terhadap engagement notifikasi.

---

## 7. Success Metrics

- **Waktu respons Cashier terhadap order baru** menurun dibanding sebelum ada notifikasi (baseline: waktu dari order dibuat → order diproses).
- **Jumlah komplain/pertanyaan customer** soal status pesanan menurun.
- **Insiden stok habis tanpa diketahui Admin** menurun (diukur dari jumlah kejadian out-of-stock yang tidak ter-notifikasi tepat waktu).

---

## 8. Open Questions

- Apakah butuh push notification di v1, atau in-app cukup untuk MVP?
- Untuk notifikasi `promo`, siapa yang trigger — manual oleh Admin, atau bisa terjadwal (scheduled campaign)?
- Berapa lama retensi data notifikasi sebelum diarsipkan/dihapus otomatis?
