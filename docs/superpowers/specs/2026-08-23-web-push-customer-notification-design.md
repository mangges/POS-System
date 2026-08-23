# Web Push Notification — Customer Page

## Problem

Customer page (`LandingPage`) sudah punya notifikasi in-app real-time lewat
Echo/Reverb: `OrderStatusUpdated` di-broadcast ke channel `table.{qr_token}`,
lalu `landing-page.blade.php` filter event itu ke order milik browser sendiri
(`sessionStorage`) dan tampilkan lewat toast `posNotifyBoard()`
(`resources/views/components/notify.blade.php`). Mekanisme ini cuma jalan
selama tab customer terbuka dan halaman menu aktif.

Customer sering menutup tab / kunci layar HP setelah checkout sambil nunggu
pesanan. Perlu notifikasi asli OS (Web Push) yang tetap muncul walau tab/
browser tertutup, dengan pesan yang sama seperti toast yang sudah ada
("Pesanan {order_number} siap diantar." dst).

Order saat ini **tidak** punya relasi ke `GuestSession` di database —
kepemilikan order cuma dilacak di `sessionStorage` browser
(`landing-page.blade.php:664-677`). Itu cukup untuk filter toast in-tab
(client-side), tapi tidak cukup untuk push: pengiriman push harus ditentukan
di server, jadi server perlu tahu order ini kepunyaan `GuestSession` mana.

## Scope

- Web Push untuk customer page saja (bukan monitor kasir/bar/kitchen —
  itu tetap notifikasi in-app seperti sekarang).
- Trigger: perubahan `Order::status` (sama seperti `OrderStatusUpdated`
  yang sudah ada) — event lain (`OrderPlaced`) tidak disentuh.
- Reuse pesan dari `OrderStatusUpdated` (`$message`/`$type`), tidak
  membuat copy baru.
- Subscribe/unsubscribe dikontrol lewat satu tombol izin di halaman menu.

Out of scope (belum diminta):

- Push untuk role Admin/Kasir (masih pakai mekanisme in-app yang ada).
- Fallback push kalau browser tidak support Push API (Safari lama, dsb) —
  toast in-tab tetap jadi fallback alami karena tidak diganti.
- Re-subscribe otomatis kalau `GuestSession` expired (1 jam) dan customer
  scan ulang — subscription lama otomatis tidak kepakai lagi karena
  `guest_session_id` order baru beda; tidak perlu migrasi subscription.

## Design

### 1. Dependency & VAPID

```bash
composer require laravel-notification-channels/webpush
php artisan vendor:publish --provider="NotificationChannels\WebPush\WebPushServiceProvider" --tag="migrations"
php artisan webpush:vapid
```

`webpush:vapid` nulis `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` ke `.env`.

### 2. Data model

- Migration dari package: tabel `push_subscriptions` (polymorphic
  `subscribable_type`/`subscribable_id`).
- Migration baru: `guest_session_id` nullable FK (`nullOnDelete`) di
  `orders`, diisi saat order dibuat.

`GuestSession`:

```php
use NotificationChannels\WebPush\HasPushSubscriptions;
use Illuminate\Notifications\Notifiable;

class GuestSession extends Model
{
    use HasFactory, Notifiable, HasPushSubscriptions;
    // ...
}
```

`Order`:

```php
public function guestSession(): BelongsTo
{
    return $this->belongsTo(GuestSession::class);
}
```

`OrderService::processOrder()` (`app/Services/Order/OrderService.php:20`)
dapat parameter baru `?int $guestSessionId`, diisi ke
`$data['guest_session_id']` di branch pembuatan order baru. Kedua call site
di `LandingPage` (`LandingPage.php:180` dan `:213`) sudah punya `$guestSession`
di scope — tinggal teruskan `$guestSession->id`.

### 3. Alur subscribe (frontend)

`public/sw.js` (service worker baru):

```js
self.addEventListener('push', (event) => {
    const data = event.data.json();
    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            data: { orderId: data.order_id },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(clients.openWindow(self.registration.scope));
});
```

Di `landing-page.blade.php`, tombol "Aktifkan notifikasi" (di dekat area
`notify.blade.php`):

```js
async function enablePush() {
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return;

    const reg = await navigator.serviceWorker.register('/sw.js');
    const sub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: '{{ config('webpush.vapid.public_key') }}',
    });

    await fetch('/menu/{{ request()->route('session_token') }}/push-subscribe', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(sub),
    });
}
```

Route baru di `routes/web.php`:

```php
Route::post('/menu/{session_token}/push-subscribe', PushSubscribeController::class);
```

`PushSubscribeController` resolve `GuestSession::where('token', $session_token)
->firstOrFail()` (404 kalau expired/salah, sama pola dengan
`LandingPage::mount()`), lalu `$guestSession->updatePushSubscription(...)`
(method bawaan trait `HasPushSubscriptions`).

Mekanisme Echo/toast yang sudah ada tidak diubah sama sekali — tetap jalan
untuk in-tab, push cuma tambahan untuk saat tab tertutup.

### 4. Alur kirim (backend)

Hook langsung di `Order::booted()` → `static::updated()`
(`app/Models/Order.php:33`), di sebelah `broadcast(new
OrderStatusUpdated($order))` yang sudah ada — konsisten dengan pola yang
sudah dipakai di model ini (bukan Listener class baru):

```php
static::updated(function (Order $order) {
    if ($order->wasChanged('status') && $order->table()->first()?->qr_token) {
        if ($order->guestSession?->isExpired()) {
            $order->guestSession->update(['expires_at' => now()->addMinutes(15)]);
        }

        $event = new OrderStatusUpdated($order);
        broadcast($event);

        if ($order->guestSession) {
            $order->guestSession->notify(new OrderStatusPushNotification($event));
        }
    }
});
```

Kenapa perlu extend: `expires_at` (1 jam sejak scan) cuma dipakai buat
blokir akses customer ke halaman menu (`LandingPage::mount()`), bukan syarat
kirim push — push di bawahnya tetap jalan walau session sudah
`isExpired()`. Tapi kalau order baru selesai/berubah status *setelah*
expired (mis. order masuk 22.50, siap diantar 22.55, expired 23.00),
customer yang mau buka lagi link menu buat lihat status malah keblokir.
Extend +15 menit tiap ada action admin nunda blokir itu selama order masih
aktif, tanpa ganggu jalur push yang memang sudah tidak bergantung ke
`isExpired()`.

`App\Notifications\OrderStatusPushNotification`:

```php
class OrderStatusPushNotification extends Notification
{
    public function __construct(private OrderStatusUpdated $event) {}

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Status pesanan')
            ->body($this->event->message)
            ->data(['order_id' => $this->event->order->id]);
    }
}
```

Reuse `$event->message` langsung — tidak duplikasi logic `match()` yang
sudah ada di `OrderStatusUpdated`.

Kalau `$order->guestSession` null (order lama sebelum kolom ini ada, atau
order dari kasir manual tanpa sesi customer) atau tidak ada subscription
tersimpan, `notify()`/package ini silent no-op — toast in-tab tetap jadi
jalur utama seperti sekarang.

Subscription yang sudah invalid (410 Gone dari push service, browser
uninstall/expire) otomatis dihapus oleh package saat pengiriman gagal —
bawaan `laravel-notification-channels/webpush`, tidak perlu kode tambahan.

## Testing

- Feature test: buat order lewat `OrderService::processOrder()` dengan
  `guestSessionId`, attach fake push subscription ke `GuestSession` itu,
  update `status` order → assert `Notification::fake()` mengirim
  `OrderStatusPushNotification` ke `GuestSession` yang benar (dan tidak ke
  `GuestSession` lain di tabel yang sama).
- Update status pada order yang `guest_session_id`-nya null tidak error
  (silent skip).
- Manual checklist: subscribe di browser real, tutup tab, ubah status order
  dari sisi kasir/admin, konfirmasi notifikasi OS muncul dengan teks yang
  sama seperti toast in-tab.
