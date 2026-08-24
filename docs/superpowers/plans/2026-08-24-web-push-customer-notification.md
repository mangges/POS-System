# Web Push Customer Notification Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Send an OS-level Web Push notification to a customer's device when their order's status changes, so they still get notified after closing the tab/locking their phone — without touching the existing Echo/toast in-app mechanism.

**Architecture:** `laravel-notification-channels/webpush` gives `GuestSession` a `push_subscriptions` table via `HasPushSubscriptions` + `Notifiable`. Orders gain a nullable `guest_session_id` FK, filled in by `OrderService::processOrder()` at order-creation time. `Order::booted()`'s existing `static::updated()` hook (which already broadcasts `OrderStatusUpdated` on status change) gets one more line: if the order has a `guestSession`, notify it via a new `OrderStatusPushNotification` that reuses `OrderStatusUpdated`'s already-computed `$message`. The browser side is a `sw.js` service worker + one "Aktifkan notifikasi" button that subscribes and POSTs the subscription to a new route.

**Tech Stack:** Laravel 13, `laravel-notification-channels/webpush` (resolves to ^11.0 against this app's `illuminate/*` ^13.8 lock — do not force 12.x), Alpine.js (already on the page), native browser Push API + Service Worker.

## Global Constraints

- Web Push is for the customer page only — Admin/Kasir/monitor notifications stay in-app (unchanged).
- Trigger is `Order::status` change only (same event as the existing `OrderStatusUpdated` broadcast) — `OrderPlaced` is not touched.
- Reuse `OrderStatusUpdated::$message`/`$type` — do not duplicate the `match()` copy logic anywhere else.
- No fallback for browsers without Push API support — the existing in-tab toast is already the fallback, nothing to add.
- No re-subscribe-on-expiry migration logic — an expired `GuestSession`'s stale subscription is simply never used again (new session, new id, old subscription row goes stale and is cleaned up automatically by the package on next failed send).
- `laravel-notification-channels/webpush` composer install resolves to version **11.0.0** in this repo (verified via `composer require --dry-run`) — do not manually pin 12.x, it needs `illuminate/notifications ^13.13` and this app is locked to `^13.8`/13.12.0 installed.
- `HasPushSubscriptions::updatePushSubscription(string $endpoint, ?string $key = null, ?string $token = null, ...)` — param names are `$key`/`$token`, not `$publicKey`/`$authToken`.

---

### Task 1: Install webpush package, migrate `push_subscriptions`, generate VAPID keys

**Files:**
- Modify: `composer.json`, `composer.lock`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_push_subscriptions_table.php` (via `vendor:publish`, timestamp assigned at publish time)
- Modify: `.env` (VAPID keys — not committed)

**Interfaces:**
- Produces: `push_subscriptions` table (columns: `id`, `subscribable_type`, `subscribable_id`, `endpoint` unique, `public_key`, `auth_token`, `content_encoding`, timestamps), `config('webpush.vapid.public_key')` / `config('webpush.vapid.private_key')` available app-wide.

- [ ] **Step 1: Install the package**

```bash
composer require laravel-notification-channels/webpush
```

Expect composer to resolve `laravel-notification-channels/webpush` to `^11.0` (not 12.x — 12.x requires `illuminate/notifications ^13.13`, this app has 13.12.0 locked). If composer tries to resolve 12.x and fails, that's expected/correct behavior — do not force it; the ^11.0 resolution is intentional per the constraint above.

- [ ] **Step 2: Publish the migration**

```bash
php artisan vendor:publish --provider="NotificationChannels\WebPush\WebPushServiceProvider" --tag="migrations"
```

This creates `database/migrations/<timestamp>_create_push_subscriptions_table.php`. Do not hand-edit it — it already creates the correct table shape (`bigIncrements('id')`, `morphs('subscribable', ...)`, `endpoint` unique, `public_key`, `auth_token`, `content_encoding`, timestamps).

- [ ] **Step 3: Generate VAPID keys**

```bash
grep -q VAPID_PUBLIC_KEY .env && echo "VAPID keys already present, skipping" || php artisan webpush:vapid
```

(If you're resuming this plan on a machine that already ran this step, `.env` already has `VAPID_PUBLIC_KEY`/`VAPID_PRIVATE_KEY` — skip regenerating so you don't invalidate keys other tasks might reference.)

- [ ] **Step 4: Run the migration**

```bash
php artisan migrate
```

- [ ] **Step 5: Verify the table exists**

```bash
php artisan tinker --execute="dump(Illuminate\Support\Facades\Schema::hasTable('push_subscriptions'));"
```

Expected: `true`

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock database/migrations/*_create_push_subscriptions_table.php
git commit -m "feat: install laravel-notification-channels/webpush"
```

(`.env` is gitignored — do not add it.)

---

### Task 2: `guest_session_id` on orders + `Order::guestSession()` + `GuestSession` push traits

**Files:**
- Create: `database/migrations/2026_08_24_140000_add_guest_session_id_to_orders_table.php`
- Modify: `app/Models/Order.php:64-67` (add relation after `table()`)
- Modify: `app/Models/GuestSession.php:1-11` (add traits)
- Test: `tests/Feature/OrderGuestSessionRelationTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `Order::guestSession(): BelongsTo` (nullable), `orders.guest_session_id` column, `GuestSession` is `Notifiable` + has `pushSubscriptions()`/`updatePushSubscription()`/`notify()` from `HasPushSubscriptions`. Later tasks rely on `$order->guestSession` and `$guestSession->notify(...)`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enum\Orders\OrderStatus;
use App\Models\GuestSession;
use App\Models\Order;
use App\Models\QrCode;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderGuestSessionRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_belongs_to_guest_session(): void
    {
        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $qrCode = QrCode::create(['table_id' => $table->id, 'qr_url' => 'https://example.test', 'file_path' => 'qrcodes/x.svg']);
        $guestSession = GuestSession::create([
            'token' => bin2hex(random_bytes(32)),
            'qr_code_id' => $qrCode->id,
            'expires_at' => now()->addHour(),
        ]);

        $order = Order::create([
            'order_number' => 'ORD100001',
            'table_id' => $table->id,
            'guest_session_id' => $guestSession->id,
            'total_amount' => 10000,
            'status' => OrderStatus::Pending,
        ]);

        $this->assertTrue($order->fresh()->guestSession->is($guestSession));
    }

    public function test_order_guest_session_is_null_by_default(): void
    {
        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $order = Order::create([
            'order_number' => 'ORD100002',
            'table_id' => $table->id,
            'total_amount' => 10000,
            'status' => OrderStatus::Pending,
        ]);

        $this->assertNull($order->fresh()->guestSession);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=OrderGuestSessionRelationTest`
Expected: FAIL — `guest_session_id` column doesn't exist / `guestSession` relation undefined.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('guest_session_id')->nullable()->after('shift_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['guest_session_id']);
            $table->dropColumn('guest_session_id');
        });
    }
};
```

Save as `database/migrations/2026_08_24_140000_add_guest_session_id_to_orders_table.php`.

- [ ] **Step 4: Add the relation to `Order`**

In `app/Models/Order.php`, right after the `table()` method (line 67):

```php
    public function guestSession(): BelongsTo
    {
        return $this->belongsTo(GuestSession::class);
    }
```

No new `use` import needed — `GuestSession` is in the same `App\Models` namespace as `Order`.

- [ ] **Step 5: Add push traits to `GuestSession`**

In `app/Models/GuestSession.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

class GuestSession extends Model
{
    use HasFactory, Notifiable, HasPushSubscriptions;
```

(Only the `use` block at the top and the trait list change — the rest of the file, including `isExpired()` and `startFor()`, is untouched.)

- [ ] **Step 6: Run migration and test**

```bash
php artisan migrate
php artisan test --filter=OrderGuestSessionRelationTest
```

Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_24_140000_add_guest_session_id_to_orders_table.php app/Models/Order.php app/Models/GuestSession.php tests/Feature/OrderGuestSessionRelationTest.php
git commit -m "feat: add orders.guest_session_id and push-subscription support on GuestSession"
```

---

### Task 3: `OrderService::processOrder()` accepts `guestSessionId`

**Files:**
- Modify: `app/Services/Order/OrderService.php:20-49`
- Modify: `app/Livewire/LandingPage/LandingPage.php:180-186` (checkout)
- Modify: `app/Livewire/LandingPage/LandingPage.php:213-220` (checkoutSplit)
- Test: `tests/Feature/Services/OrderServiceGuestSessionTest.php`

**Interfaces:**
- Consumes: `Order::guestSession()` from Task 2.
- Produces: `OrderService::processOrder(..., ?int $shiftId = null, ?int $guestSessionId = null)` — new trailing named param, existing positional callers (`Cashier.php`) are unaffected since they never pass it.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Services;

use App\Models\GuestSession;
use App\Models\QrCode;
use App\Models\Table;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderServiceGuestSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_order_stores_guest_session_id_on_new_order(): void
    {
        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $qrCode = QrCode::create(['table_id' => $table->id, 'qr_url' => 'https://example.test', 'file_path' => 'qrcodes/x.svg']);
        $guestSession = GuestSession::create([
            'token' => bin2hex(random_bytes(32)),
            'qr_code_id' => $qrCode->id,
            'expires_at' => now()->addHour(),
        ]);

        $order = app(OrderService::class)->processOrder(
            [],
            $table->id,
            'Budi',
            'dine-in',
            null,
            'cash',
            guestSessionId: $guestSession->id,
        );

        $this->assertSame($guestSession->id, $order->fresh()->guest_session_id);
    }

    public function test_process_order_without_guest_session_id_leaves_it_null(): void
    {
        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);

        $order = app(OrderService::class)->processOrder([], $table->id, 'Budi', 'dine-in');

        $this->assertNull($order->fresh()->guest_session_id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=OrderServiceGuestSessionTest`
Expected: FAIL — unknown named parameter `guestSessionId`.

- [ ] **Step 3: Add the parameter in `OrderService::processOrder()`**

In `app/Services/Order/OrderService.php`, change the signature (line 20):

```php
    public function processOrder(array $cartItems, ?int $tableId = null, ?string $customerName = null, ?string $orderType = null, ?int $activeDraft = null, string $paymentMethod = 'cash', ?int $shiftId = null, ?int $guestSessionId = null): Order
```

And inside the `else` branch (lines 39-47), add `$guestSessionId` to the `use()` clause and set the field:

```php
        } else {
            $order = DB::transaction(function () use ($data, $tableId, $shiftId, $guestSessionId) {
                $data['table_id'] = $tableId;
                $data['customer_id'] = null;
                $data['status'] = OrderStatus::Pending;
                $data['payment_id'] = null;
                $data['order_number'] = $this->createOrderNumber();
                $data['user_id'] = Auth::id();
                $data['shift_id'] = $shiftId;
                $data['guest_session_id'] = $guestSessionId;

                return Order::create($data);
            });
        }
```

The `$activeDraft` branch (existing draft update) is untouched — `guest_session_id` is only set at order-creation time, per spec.

- [ ] **Step 4: Wire it up in `LandingPage::checkout()`**

In `app/Livewire/LandingPage/LandingPage.php`, the `checkout()` call (around line 180):

```php
        $order = $this->orderService->processOrder(
            $this->cart,
            $guestSession->qrCode->table_id,
            $this->customerName,
            $this->orderType,
            null,
            $this->paymentMethod,
            guestSessionId: $guestSession->id,
        );
```

- [ ] **Step 5: Wire it up in `LandingPage::checkoutSplit()`**

Same file, the `checkoutSplit()` call (around line 213):

```php
                $order = $this->orderService->processOrder(
                    $this->buildSplitCartItems($index),
                    $guestSession->qrCode->table_id,
                    $group['name'],
                    $this->orderType,
                    null,
                    $this->paymentMethod,
                    guestSessionId: $guestSession->id,
                );
```

- [ ] **Step 6: Run tests**

```bash
php artisan test --filter=OrderServiceGuestSessionTest
php artisan test --filter=GuestSessionCheckoutTest
```

Expected: all PASS (the second confirms the existing checkout flows still work unchanged).

- [ ] **Step 7: Commit**

```bash
git add app/Services/Order/OrderService.php app/Livewire/LandingPage/LandingPage.php tests/Feature/Services/OrderServiceGuestSessionTest.php
git commit -m "feat: record guest_session_id when a customer places an order"
```

---

### Task 4: `OrderStatusPushNotification`

**Files:**
- Create: `app/Notifications/OrderStatusPushNotification.php`
- Test: `tests/Feature/OrderStatusPushNotificationTest.php`

**Interfaces:**
- Consumes: `App\Events\OrderStatusUpdated` (public `$message`, public `$order`).
- Produces: `OrderStatusPushNotification` — constructed with an `OrderStatusUpdated $event`, `via()` returns `[WebPushChannel::class]`. Task 5 dispatches this via `$guestSession->notify(new OrderStatusPushNotification($event))`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enum\Orders\OrderStatus;
use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Models\Table;
use App\Notifications\OrderStatusPushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_web_push_reuses_event_message(): void
    {
        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $order = Order::create([
            'order_number' => 'ORD200001',
            'table_id' => $table->id,
            'total_amount' => 10000,
            'status' => OrderStatus::Ready,
        ]);

        $event = new OrderStatusUpdated($order);
        $notification = new OrderStatusPushNotification($event);

        $payload = $notification->toWebPush(null, $notification)->toArray();

        $this->assertSame('Status pesanan', $payload['title']);
        $this->assertSame($event->message, $payload['body']);
        $this->assertSame(['order_id' => $order->id], $payload['data']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=OrderStatusPushNotificationTest`
Expected: FAIL — class `App\Notifications\OrderStatusPushNotification` not found.

- [ ] **Step 3: Write the notification**

```php
<?php

namespace App\Notifications;

use App\Events\OrderStatusUpdated;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

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

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=OrderStatusPushNotificationTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Notifications/OrderStatusPushNotification.php tests/Feature/OrderStatusPushNotificationTest.php
git commit -m "feat: add OrderStatusPushNotification reusing OrderStatusUpdated's message"
```

---

### Task 5: Send the push from `Order::booted()` + extend `GuestSession` expiry

**Files:**
- Modify: `app/Models/Order.php:1-13` (import), `app/Models/Order.php:33-37` (hook)
- Test: `tests/Feature/OrderStatusPushDispatchTest.php`

**Interfaces:**
- Consumes: `App\Notifications\OrderStatusPushNotification` (Task 4), `Order::guestSession()` (Task 2), `GuestSession::isExpired()` (existing).
- Produces: nothing new consumed by later tasks — this is the last backend wiring point.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Enum\Orders\OrderStatus;
use App\Models\GuestSession;
use App\Models\Order;
use App\Models\QrCode;
use App\Models\Table;
use App\Notifications\OrderStatusPushNotification;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderStatusPushDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function createGuestSession(Table $table): GuestSession
    {
        $qrCode = QrCode::create(['table_id' => $table->id, 'qr_url' => 'https://example.test', 'file_path' => 'qrcodes/x.svg']);

        return GuestSession::create([
            'token' => bin2hex(random_bytes(32)),
            'qr_code_id' => $qrCode->id,
            'expires_at' => now()->addHour(),
        ]);
    }

    public function test_status_change_sends_push_to_owning_guest_session_only(): void
    {
        Notification::fake();

        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $guestSession = $this->createGuestSession($table);
        $otherGuestSession = $this->createGuestSession($table);

        $order = app(OrderService::class)->processOrder(
            [], $table->id, 'Budi', 'dine-in', null, 'cash', guestSessionId: $guestSession->id
        );

        $order->update(['status' => OrderStatus::Ready]);

        Notification::assertSentTo($guestSession, OrderStatusPushNotification::class);
        Notification::assertNotSentTo($otherGuestSession, OrderStatusPushNotification::class);
    }

    public function test_status_change_without_guest_session_does_not_error(): void
    {
        Notification::fake();

        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $order = Order::create([
            'order_number' => 'ORD300002',
            'table_id' => $table->id,
            'total_amount' => 10000,
            'status' => OrderStatus::Pending,
        ]);

        $order->update(['status' => OrderStatus::Ready]);

        Notification::assertNothingSent();
    }

    public function test_expired_guest_session_is_extended_on_status_change(): void
    {
        Notification::fake();

        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $guestSession = $this->createGuestSession($table);

        $order = app(OrderService::class)->processOrder(
            [], $table->id, 'Budi', 'dine-in', null, 'cash', guestSessionId: $guestSession->id
        );

        $guestSession->update(['expires_at' => now()->subMinute()]);

        $order->update(['status' => OrderStatus::Ready]);

        $this->assertTrue($guestSession->fresh()->expires_at->isFuture());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=OrderStatusPushDispatchTest`
Expected: FAIL — no push sent, `expires_at` not extended.

- [ ] **Step 3: Import the notification in `Order.php`**

Add to the `use` block at the top of `app/Models/Order.php` (after line 12):

```php
use App\Notifications\OrderStatusPushNotification;
```

- [ ] **Step 4: Extend the `static::updated()` hook**

Replace lines 33-37 of `app/Models/Order.php`:

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

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test --filter=OrderStatusPushDispatchTest
php artisan test --filter=OrderBroadcastTest
```

Expected: all PASS (the second confirms the existing broadcast behavior is unchanged).

- [ ] **Step 6: Commit**

```bash
git add app/Models/Order.php tests/Feature/OrderStatusPushDispatchTest.php
git commit -m "feat: dispatch web push on order status change, extend guest session near expiry"
```

---

### Task 6: `PushSubscribeController` + route

**Files:**
- Create: `app/Http/Controllers/PushSubscribeController.php`
- Modify: `routes/web.php:1-10` (import), `routes/web.php:56-57` (route)
- Test: `tests/Feature/PushSubscribeControllerTest.php`

**Interfaces:**
- Consumes: `GuestSession::updatePushSubscription()` (from `HasPushSubscriptions`, Task 2).
- Produces: `POST /menu/{session_token}/push-subscribe` — consumed by the frontend in Task 7.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\GuestSession;
use App\Models\QrCode;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscribeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createGuestSession(Table $table): GuestSession
    {
        $qrCode = QrCode::create(['table_id' => $table->id, 'qr_url' => 'https://example.test', 'file_path' => 'qrcodes/x.svg']);

        return GuestSession::create([
            'token' => bin2hex(random_bytes(32)),
            'qr_code_id' => $qrCode->id,
            'expires_at' => now()->addHour(),
        ]);
    }

    public function test_it_stores_push_subscription_for_guest_session(): void
    {
        $table = Table::create(['name' => 'T1', 'number' => '1', 'barcode' => uniqid()]);
        $guestSession = $this->createGuestSession($table);

        $response = $this->postJson("/menu/{$guestSession->token}/push-subscribe", [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-token'],
        ]);

        $response->assertOk();
        $this->assertSame(1, $guestSession->pushSubscriptions()->count());
        $this->assertSame('https://fcm.googleapis.com/fcm/send/abc123', $guestSession->pushSubscriptions()->first()->endpoint);
    }

    public function test_it_404s_for_unknown_session_token(): void
    {
        $response = $this->postJson('/menu/does-not-exist/push-subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        ]);

        $response->assertNotFound();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PushSubscribeControllerTest`
Expected: FAIL — route not defined (404 on both, so the first test fails on `assertOk`).

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\GuestSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscribeController extends Controller
{
    public function __invoke(Request $request, string $session_token): JsonResponse
    {
        $guestSession = GuestSession::where('token', $session_token)->firstOrFail();

        $data = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['nullable', 'string'],
            'keys.auth' => ['nullable', 'string'],
        ]);

        $guestSession->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'] ?? null,
            $data['keys']['auth'] ?? null,
        );

        return response()->json(['status' => 'ok']);
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, add the import next to the other controller/component imports (near line 4):

```php
use App\Http\Controllers\PushSubscribeController;
```

And add the route right after the `menu` route (line 56):

```php
Route::get('/menu/{session_token}', LandingPage::class)->name('menu');
Route::post('/menu/{session_token}/push-subscribe', PushSubscribeController::class)->name('menu.push-subscribe');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=PushSubscribeControllerTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PushSubscribeController.php routes/web.php tests/Feature/PushSubscribeControllerTest.php
git commit -m "feat: add push-subscribe endpoint for the customer menu page"
```

---

### Task 7: Service worker + "Aktifkan notifikasi" button

**Files:**
- Create: `public/sw.js`
- Modify: `resources/views/livewire/landing-page/landing-page.blade.php:5-9` (navbar button)
- Modify: `resources/views/livewire/landing-page/landing-page.blade.php:679-694` (register `enablePush()`)

**Interfaces:**
- Consumes: `config('webpush.vapid.public_key')` (Task 1), `POST /menu/{session_token}/push-subscribe` (Task 6).
- Produces: nothing consumed by other tasks — this is the last task.

No automated test: this repo has no JS test runner (`package.json` has no `vitest`/`jest`, no existing spec file tests any of the page's Alpine/JS), and the spec itself designates this as a manual check. Verify manually per Step 4 below instead of inventing new JS test infrastructure for one button handler.

- [ ] **Step 1: Create the service worker**

`public/sw.js` (must live at the domain root so its default scope covers the whole site):

```js
self.addEventListener('push', (event) => {
    const payload = event.data.json();
    event.waitUntil(
        self.registration.showNotification(payload.title, {
            body: payload.body,
            data: { orderId: payload.data?.order_id },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(clients.openWindow(self.registration.scope));
});
```

Note: the push payload the browser receives is `json_encode($message->toArray())` from `WebPushChannel::send()` — i.e. `{title, body, data: {order_id}}`. `order_id` is nested under `data`, not top-level, hence `payload.data?.order_id` (the spec draft had `data.order_id` directly, which would read `undefined`).

- [ ] **Step 2: Add the button to the navbar**

In `resources/views/livewire/landing-page/landing-page.blade.php`, the navbar currently reads:

```html
    <nav class="navbar">
        <div class="table-number">
            <span>{{ $table->name }}</span>
        </div>
        <x-notification-bell :qr-token="$table->qr_token" />
    </nav>
```

Change to:

```html
    <nav class="navbar">
        <div class="table-number">
            <span>{{ $table->name }}</span>
        </div>
        <button type="button" class="pos-bell-trigger" style="margin-left:auto" onclick="enablePush()" aria-label="Aktifkan notifikasi">
            <i class="bi bi-bell-fill"></i>
        </button>
        <x-notification-bell :qr-token="$table->qr_token" />
    </nav>
```

Reuses `.pos-bell-trigger` (defined in `resources/views/components/notification-bell.blade.php`'s `<style>` block, which is plain global CSS on this page) instead of writing new CSS — same 40px circular icon button as the bell next to it. The navbar's `gap: 0.75rem` (`resources/css/landing-page.css:61`) spaces the two buttons; `margin-left:auto` on this button pushes both it and the bell to the right edge.

- [ ] **Step 3: Register `enablePush()`**

In the same file, inside the existing `<script>` block right before `@endpush` (currently ends around line 694), add a new plain `<script>` tag (plain, not `@script` — a global function definition is idempotent to redefine on every `wire:navigate`, unlike an event listener):

```html
<script>
    window.enablePush = async function enablePush() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

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
    };
</script>
```

- [ ] **Step 4: Manual verification**

1. `npm run dev` (or `build`) + serve the app over the domain the QR points to (Push API requires a secure context — `localhost` counts, plain HTTP LAN IPs don't).
2. Scan a table's QR / open `/menu/{session_token}` in Chrome.
3. Click "Aktifkan notifikasi", accept the browser permission prompt.
4. Confirm a row appears: `php artisan tinker --execute="dump(App\Models\GuestSession::latest()->first()->pushSubscriptions);"`
5. Close the tab entirely.
6. From the cashier/admin side, change that order's status (accept / mark ready).
7. Confirm an OS notification appears with the same text as the existing in-tab toast, and clicking it opens the menu page.

- [ ] **Step 5: Commit**

```bash
git add public/sw.js resources/views/livewire/landing-page/landing-page.blade.php
git commit -m "feat: add service worker and subscribe button for customer web push"
```
