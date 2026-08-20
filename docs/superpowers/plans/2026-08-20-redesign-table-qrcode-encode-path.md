# Implementasi QR Session Token dengan Expiry

# Skills
/ponytail:ponytail
/superpowers:using-superpowers
/caveman:caveman
/commit-work:commit-work

# Commit skills
Per context, oneline, no co-author
jangan langsung commit, biarkan saya cek dulu


Saya ingin menambahkan mekanisme **temporary guest session berbasis QR Code** pada sistem menu/order yang sudah ada.

## Tujuan

Saat customer melakukan scan QR Code fisik:

1. QR Code tetap menggunakan URL permanen yang sama.
2. Server memvalidasi QR Code tersebut.
3. Server membuat **random session token** baru.
4. User diarahkan ke URL menu menggunakan token tersebut.
5. Session token hanya berlaku selama **1 jam sejak scan**.
6. Setelah expired, user tidak dapat menggunakan session tersebut lagi dan harus **scan QR Code kembali**.
7. Jangan mengubah URL QR Code fisik yang sudah ada.

Contoh flow:

```text
QR fisik
    ↓
/q/{qr_code}
    ↓
validasi QR
    ↓
buat Guest Session
    ↓
/menu/{session_token}
    ↓
akses menu + order
```

Contoh:

```text
/q/ABC123
    ↓
/menu/8f92a7c1...
```

Token tersebut valid selama 1 jam.

Setelah expired:

```text
/menu/8f92a7c1...
    ↓
Session expired
    ↓
User diminta scan QR kembali
```

---

## 1. Audit terlebih dahulu

Sebelum melakukan perubahan kode:

* Pelajari struktur project.
* Cari model/table yang saat ini menyimpan QR Code.
* Cari route/controller yang menangani QR Code.
* Cari bagaimana QR Code saat ini mengarah ke menu.
* Cari bagaimana proses order saat ini mendapatkan informasi merchant/booth/table/QR.
* Cari apakah sudah ada konsep session/token yang bisa digunakan kembali.
* Cari middleware/authentication yang sudah tersedia.

**Jangan langsung membuat migration/model baru sebelum memahami struktur yang sudah ada.**

Pertahankan arsitektur existing sebisa mungkin.

---

## 2. Buat Guest Session

Jika belum ada mekanisme yang sesuai, buat tabel untuk temporary guest session.

Minimal data:

```text
id
token
qr_code_id
expires_at
created_at
updated_at
```

Token harus dibuat menggunakan cryptographically secure random value.

Contoh PHP:

```php
bin2hex(random_bytes(32))
```

Jangan menggunakan:

```php
qr_code_id
timestamp
incrementing ID
hash(qr_code_id)
```

sebagai token.

Tujuannya agar token tidak mudah ditebak dan setiap scan menghasilkan token berbeda.

---

## 3. QR Code flow

URL QR Code fisik tidak berubah.

Misalnya:

```text
/q/ABC123
```

Ketika endpoint tersebut diakses:

1. Cari QR Code berdasarkan identifier.
2. Validasi QR Code.
3. Generate guest session baru.
4. Set:

```text
expires_at = now() + 1 hour
```

5. Redirect ke:

```text
/menu/{token}
```

Contoh:

```text
/q/ABC123
      ↓
GuestSession
token = random token
expires_at = 1 hour
      ↓
/menu/{token}
```

---

## 4. Menu menggunakan session token

Endpoint menu harus menggunakan session token, bukan QR Code ID secara langsung.

Contoh:

```text
/menu/{token}
```

Saat endpoint dipanggil:

1. Cari GuestSession berdasarkan token.
2. Pastikan session masih valid.
3. Pastikan `expires_at > now()`.
4. Ambil QR Code melalui relationship.
5. Dari QR Code, ambil data menu/merchant/table yang sesuai dengan arsitektur existing.
6. Render menu.

Jika token tidak ditemukan:

```text
404 / invalid session
```

Jika token ditemukan tetapi expired:

```text
Session expired.
Please scan the QR code again.
```

Gunakan response/redirect yang sesuai dengan pola existing project.

---

## 5. Order harus menggunakan Guest Session

Pastikan proses membuat order **tidak hanya menerima QR Code ID atau table ID dari frontend**.

Order harus mendapatkan konteks QR/session dari token yang sudah divalidasi server.

Contoh:

```text
Request
   ↓
session token
   ↓
GuestSession
   ↓
QR Code
   ↓
Merchant / Booth / Table
   ↓
Create Order
```

Jangan mempercayai value seperti:

```text
qr_code_id
table_id
merchant_id
```

yang dikirim langsung dari frontend jika value tersebut sebenarnya dapat ditentukan dari session.

Tujuannya mencegah user mengganti ID melalui request dan membuat order untuk QR/table lain.

---

## 6. Session expiry

Session berlaku:

```text
1 hour from scan
```

Bukan 1 jam dari request terakhir.

Contoh:

```text
14:00 scan
expires_at = 15:00
```

Jika user membuka menu pada:

```text
14:30 → valid
14:59 → valid
15:00+ → expired
```

Jangan memperpanjang `expires_at` setiap kali user membuka menu, refresh, atau melakukan order.

---

## 7. Behavior setelah expired

Jika user masih memiliki tab:

```text
/menu/old-token
```

setelah 1 jam:

* token tidak boleh digunakan lagi.
* user tidak boleh membuat order menggunakan token tersebut.
* user diarahkan untuk scan QR kembali.

Scan ulang akan menghasilkan token baru:

```text
old token
8f92...
expired

scan QR again

new token
71ab...
valid for 1 hour
```

---

## 8. Multiple users

QR Code yang sama harus dapat digunakan oleh banyak user secara bersamaan.

Contoh:

```text
User A
scan QR
→ token A

User B
scan QR
→ token B

User C
scan QR
→ token C
```

Ketiga session tidak boleh saling menimpa.

Semua dapat mengakses menu yang sama tetapi menggunakan session token berbeda.

---

## 9. Security requirements

Perhatikan hal berikut:

* Token harus cryptographically random.
* Jangan gunakan sequential ID sebagai public token.
* Jangan expose internal database ID jika tidak diperlukan.
* Jangan percaya `qr_code_id`, `table_id`, atau `merchant_id` dari client jika dapat diturunkan dari session.
* Token expired harus ditolak di server.
* Jangan hanya mengandalkan JavaScript/frontend untuk expiry.
* Pastikan token tidak dapat digunakan untuk mengakses QR/menu lain.
* Pastikan user tidak dapat mengganti token dengan token yang mudah ditebak.
* Jangan menyimpan token dalam bentuk yang memungkinkan enumeration.
* Jika memungkinkan, gunakan database index/unique constraint pada token.

---

## 10. Cleanup expired session

Evaluasi mekanisme cleanup session expired.

Jangan menghapus session secara langsung ketika expired jika data tersebut masih dibutuhkan untuk audit/order history.

Jika cleanup diperlukan, gunakan mekanisme scheduled job/command untuk menghapus session yang memang sudah tidak dibutuhkan.

Expiry tetap harus divalidasi pada setiap request walaupun record expired masih ada di database.

---

## 11. Testing

Buat/ubah automated test untuk memastikan:

### Test 1 — Scan QR

```text
GET /q/ABC123
```

Expected:

```text
redirect → /menu/{random-token}
```

---

### Test 2 — Token berbeda setiap scan

Scan QR yang sama dua kali:

```text
token A != token B
```

Keduanya harus valid secara bersamaan.

---

### Test 3 — Token valid

Token yang belum expired:

```text
GET /menu/{token}
```

Expected:

```text
200
```

---

### Test 4 — Token expired

Buat session dengan:

```text
expires_at < now()
```

Kemudian akses:

```text
/menu/{token}
```

Expected:

```text
session expired / redirect scan QR
```

---

### Test 5 — Tidak bisa order dengan expired session

Gunakan token expired untuk membuat order.

Expected:

```text
request rejected
```

Tidak boleh ada order yang dibuat.

---

### Test 6 — Order menggunakan session yang valid

Gunakan token valid.

Expected:

```text
order berhasil dibuat
```

dan order mendapatkan merchant/table/QR context yang benar dari session.

---

### Test 7 — User tidak bisa mengganti QR context

Pastikan user tidak bisa mengubah request seperti:

```text
qr_code_id = another_qr
table_id = another_table
merchant_id = another_merchant
```

untuk memanipulasi tujuan order.

Server harus menentukan context berdasarkan GuestSession.

---

## 12. Jangan melakukan perubahan yang tidak diperlukan

Penting:

* Jangan rewrite sistem QR yang sudah berjalan jika tidak diperlukan.
* Jangan mengubah struktur order existing tanpa alasan.
* Jangan mengubah UI menu secara besar-besaran.
* Jangan mengubah flow pembayaran yang sudah ada.
* Jangan menambahkan authentication/login customer.
* Jangan menggunakan GPS/location sebagai requirement.
* Fokus hanya pada **QR → temporary guest session → menu → order → expiry**.

Sebelum coding, jelaskan terlebih dahulu:

1. Struktur existing yang ditemukan.
2. Model/table yang akan digunakan.
3. Route yang akan diubah.
4. Controller/service yang akan diubah.
5. Migration yang diperlukan.
6. Bagaimana session token akan digunakan pada proses order.

Setelah itu implementasikan secara bertahap dan jalankan test yang relevan.

Di akhir, berikan ringkasan:

* file yang berubah
* migration yang dibuat
* route yang berubah
* flow baru
* test yang dijalankan
* potensi issue yang masih perlu diperhatikan.
