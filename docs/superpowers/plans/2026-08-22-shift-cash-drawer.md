# Shift & Cash Drawer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Kasir wajib buka shift (modal awal) sebelum bisa transaksi di Cashier, bisa cash in/out selama shift, dan tutup shift dengan rekonsiliasi kas otomatis (expected vs actual). Admin bisa lihat riwayat shift + cash movements di Filament, plus halaman Shift Report dengan export CSV/PDF.

**Architecture:** Dua tabel baru (`shifts`, `cash_movements`) plus kolom `orders.shift_id`. `ShiftService` (pola sama seperti `OrderService`) mengurus buka/tutup shift dan hitung `expected_cash`. `Cashier` Livewire component jadi gate: `mount()` cek shift `open` milik user, kalau tidak ada, view merender layar blocking "Buka Shift" (bukan modal) alih-alih POS. Order yang dibuat lewat `OrderService::processOrder()` selama shift jalan otomatis dapat `shift_id`. Admin side: `ShiftResource` (Filament, read-only — index + view saja, tanpa create/edit) dengan `CashMovementsRelationManager`, dan `ShiftReport` page yang mengikuti pola persis `SalesReport`/`ProductReport` yang sudah ada (`HasReportPeriod` trait, CSV via `league/csv`, PDF via `barryvdh/laravel-dompdf`).

**Tech Stack:** Laravel, Livewire 3, Filament 5 (schema-based `Schema`/`Table`/infolist API), PHPUnit (`RefreshDatabase` + `Livewire::test()`), SQLite in-memory for tests.

## Global Constraints

- Spec doc: `docs/superpowers/specs/2026-08-22-shift-cash-drawer-design.md` — this plan implements it in full; where this plan and the spec conflict, ask the human partner which governs.
- Enum convention: `App\Enum\<Domain>\<Name>`, backed `enum ... : string`, implements `Filament\Support\Contracts\HasLabel, HasColor` with `getLabel()`/`getColor()` match expressions — same style as `App\Enum\Orders\OrderStatus` (`app/Enum/Orders/OrderStatus.php`), not the plain `PaymentMethod` style.
- Model convention: `use HasFactory;`, `protected $fillable`, `protected $casts` with enum classes, relation methods return typed (`BelongsTo`/`HasMany`) — matches `app/Models/Payment.php` and `app/Models/StockMovement.php`.
- Service convention: plain class in `app/Services/<Domain>/<Name>Service.php` (namespaced, e.g. `App\Services\Shift\ShiftService`), constructor-injected where needed, thrown errors are plain `throw new \Exception('message')` — matches `app/Services/Order/OrderService.php`.
- Livewire property/method convention in `Cashier`: a modal is a `public bool $show<Name>Modal = false;` property plus `open<Name>Modal()`/`close<Name>Modal()` methods — matches existing `showDraftsModal`/`openDraftsModal()`/`closeDraftsModal()` and `showQrisPreviewModal`/`openQrisPreviewModal()`/`closeQrisPreviewModal()`.
- Filament read-only resource convention: only register the page keys that should exist in `getPages()` (no `'create'`/`'edit'` keys = those routes 404 automatically) — matches `app/Filament/Resources/StockMovements/StockMovementResource.php`. Directory layout: `Pages/`, `Schemas/` (form/infolist), `Tables/`, `RelationManagers/` — one class per file, no `Schemas/` file needed if the schema has zero fields.
- Filament pages/resources are auto-discovered (`app/Providers/Filament/AdminPanelProvider.php:39-40`) — no manual registration needed, just put files in the right namespaced directory.
- Navigation groups: use `'Transaction'` for `ShiftResource` (matches `OrderResource`/`PaymentResource`), `'Reports'` for `ShiftReport` (matches `SalesReport`/`ProductReport`).
- CSS: add new classes to `resources/css/cashier.css` reusing the existing CSS custom properties defined in its `:root` block (`--color-primary`, `--color-bg-surface`, `--color-text-main`, `--color-border`, `--radius-sm`, etc.) — don't hardcode new hex colors that duplicate an existing token.
- Test convention: `RefreshDatabase` trait per test class, `$this->actingAs(User::factory()->create())` for auth, `Livewire::test(Cashier::class)->call(...)` / `->set(...)` / `->assertSet(...)` for Livewire, plain `Order::create([...])`/`Payment::create([...])` (no factories for domain models) — matches `tests/Feature/Livewire/Pos/CashierSplitBillTest.php` and `tests/Feature/Filament/SalesReportPageTest.php`.
- No PHP doc blocks, no comments except where a non-obvious constraint needs explaining — matches existing code style.
- Every new non-trivial method gets a PHPUnit test.
- **UI/UX polish** (Cashier blocking screen, cash-in/out modal, tutup-shift form, Filament resource/report page): the code in this plan is functionally complete and testable as-is; per the spec's own Implementation Guidelines, run the result through `ui-ux-pro-max`/`frontend-design` for visual polish if the task reviewer or human partner flags it — this is not a blocking requirement of any task's spec-compliance check below.
- Money values: `decimal:2` casts throughout, matching `Order`/`Payment`. `expected_cash`/`actual_cash`/`difference`/`opening_cash` are all `decimal(12,2)` per the spec.

---

### Task 1: Migrations, enums, `Shift`/`CashMovement` models, `Order.shift_id`

**Files:**
- Create: `database/migrations/2026_08_22_000001_create_shifts_table.php`
- Create: `database/migrations/2026_08_22_000002_create_cash_movements_table.php`
- Create: `database/migrations/2026_08_22_000003_add_shift_id_to_orders_table.php`
- Create: `app/Enum/Shifts/ShiftStatus.php`
- Create: `app/Enum/Shifts/CashMovementType.php`
- Create: `app/Models/Shift.php`
- Create: `app/Models/CashMovement.php`
- Modify: `app/Models/Order.php:40-54` (`$fillable`), `:78-86` (add `shift()` relation after `items()`)
- Test: `tests/Feature/Models/ShiftTest.php`

**Interfaces:**
- Produces: `Shift` model (`user()`, `cashMovements()`, `orders()` relations; fillable `user_id, opening_cash, expected_cash, actual_cash, difference, status, note, opened_at, closed_at`; `status` cast to `ShiftStatus`). `CashMovement` model (`shift()`, `creator()` relations; fillable `shift_id, type, amount, reason, created_by`; `type` cast to `CashMovementType`). `Order::shift(): BelongsTo` and `shift_id` in `Order`'s fillable — later tasks depend on all of this exactly as named.
- Consumes: nothing from other tasks (foundational).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Models/ShiftTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Enum\Orders\OrderStatus;
use App\Enum\Shifts\CashMovementType;
use App\Enum\Shifts\ShiftStatus;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_can_be_created_with_expected_casts(): void
    {
        $user = User::factory()->create();

        $shift = Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 100000,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);

        $this->assertInstanceOf(ShiftStatus::class, $shift->status);
        $this->assertSame(ShiftStatus::Open, $shift->status);
        $this->assertSame(100000.0, (float) $shift->opening_cash);
        $this->assertTrue($shift->user->is($user));
    }

    public function test_shift_has_many_cash_movements(): void
    {
        $user = User::factory()->create();
        $shift = Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 0,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);

        $movement = CashMovement::create([
            'shift_id' => $shift->id,
            'type' => CashMovementType::In,
            'amount' => 50000,
            'reason' => 'Setoran modal tambahan',
            'created_by' => $user->id,
        ]);

        $this->assertInstanceOf(CashMovementType::class, $movement->type);
        $this->assertTrue($shift->cashMovements->first()->is($movement));
        $this->assertTrue($movement->shift->is($shift));
        $this->assertTrue($movement->creator->is($user));
    }

    public function test_shift_has_many_orders_via_shift_id(): void
    {
        $user = User::factory()->create();
        $shift = Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 0,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);

        $order = Order::create([
            'order_number' => 'ORD-SHIFT-1',
            'total_amount' => 10000,
            'tax' => 0,
            'discount' => 0,
            'status' => OrderStatus::Completed,
            'order_type' => 'dine_in',
            'shift_id' => $shift->id,
        ]);

        $this->assertTrue($shift->orders->first()->is($order));
        $this->assertTrue($order->shift->is($shift));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ShiftTest`
Expected: FAIL (class `App\Models\Shift` not found / table `shifts` doesn't exist)

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_08_22_000001_create_shifts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->decimal('opening_cash', 12, 2);
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('actual_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->string('status')->default('open');
            $table->text('note')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
```

`database/migrations/2026_08_22_000002_create_cash_movements_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 12, 2);
            $table->string('reason');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
```

`database/migrations/2026_08_22_000003_add_shift_id_to_orders_table.php`:

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
            $table->foreignId('shift_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropColumn('shift_id');
        });
    }
};
```

- [ ] **Step 4: Write the enums**

`app/Enum/Shifts/ShiftStatus.php`:

```php
<?php

namespace App\Enum\Shifts;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum ShiftStatus: string implements HasLabel, HasColor
{
    case Open   = 'open';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match($this) {
            self::Open   => 'Open',
            self::Closed => 'Closed',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::Open   => 'success',
            self::Closed => 'gray',
        };
    }
}
```

`app/Enum/Shifts/CashMovementType.php`:

```php
<?php

namespace App\Enum\Shifts;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum CashMovementType: string implements HasLabel, HasColor
{
    case In  = 'in';
    case Out = 'out';

    public function getLabel(): string
    {
        return match($this) {
            self::In  => 'Cash In',
            self::Out => 'Cash Out',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::In  => 'success',
            self::Out => 'danger',
        };
    }
}
```

- [ ] **Step 5: Write the models**

`app/Models/Shift.php`:

```php
<?php

namespace App\Models;

use App\Enum\Shifts\ShiftStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'opening_cash',
        'expected_cash',
        'actual_cash',
        'difference',
        'status',
        'note',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'status' => ShiftStatus::class,
        'opening_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'actual_cash' => 'decimal:2',
        'difference' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
```

`app/Models/CashMovement.php`:

```php
<?php

namespace App\Models;

use App\Enum\Shifts\CashMovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_id',
        'type',
        'amount',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'type' => CashMovementType::class,
        'amount' => 'decimal:2',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

- [ ] **Step 6: Update the `Order` model**

In `app/Models/Order.php`, add `'shift_id'` to `$fillable` (after `'user_id',` at line 45):

```php
    protected $fillable = [
        'order_number',
        'table_id',
        'customer_id',
        'customer_name',
        'user_id',
        'shift_id',
        'total_amount',
        'tax',
        'discount',
        'status',
        'payment_id',
        'order_type',
        'customer_name',
        'token',
    ];
```

Add a `shift()` relation after `items()` (after line 81):

```php
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
```

- [ ] **Step 7: Run migrations and tests**

Run: `php artisan test --filter=ShiftTest`
Expected: PASS (3/3)

- [ ] **Step 8: Commit**

```bash
git add database/migrations app/Enum/Shifts app/Models/Shift.php app/Models/CashMovement.php app/Models/Order.php tests/Feature/Models/ShiftTest.php
git commit -m "feat: add shift and cash movement schema, enums, and models"
```

---

### Task 2: `ShiftService::open()` / `close()`

**Files:**
- Create: `app/Services/Shift/ShiftService.php`
- Test: `tests/Feature/Services/ShiftServiceTest.php`

**Interfaces:**
- Consumes: `Shift`, `CashMovement`, `ShiftStatus`, `CashMovementType` from Task 1; `App\Models\Payment`, `App\Enum\Payments\PaymentMethod`, `App\Enum\Orders\PaymentStatus`, `App\Models\Order`.
- Produces: `ShiftService::open(int $userId, float $openingCash): Shift` (throws `\Exception` if the user already has a `Shift` with `status === ShiftStatus::Open`). `ShiftService::previewExpectedCash(Shift $shift): float` (the expected-cash formula, computed without mutating the shift). `ShiftService::close(Shift $shift, float $actualCash, ?string $note = null): Shift` (calls `previewExpectedCash()` internally). Cashier Task 3 calls `open()`, and Task 6 calls `previewExpectedCash()` and `close()` — by exact name/signature.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Services/ShiftServiceTest.php`:

```php
<?php

namespace Tests\Feature\Services;

use App\Enum\Orders\OrderStatus;
use App\Enum\Orders\PaymentStatus;
use App\Enum\Payments\PaymentMethod;
use App\Enum\Shifts\CashMovementType;
use App\Enum\Shifts\ShiftStatus;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\User;
use App\Services\Shift\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrderWithPayment(int $shiftId, string $paymentMethod, string $paymentStatus, float $amount): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'total_amount' => $amount,
            'tax' => 0,
            'discount' => 0,
            'status' => OrderStatus::Completed,
            'order_type' => 'dine_in',
            'shift_id' => $shiftId,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'payment_method' => $paymentMethod,
            'amount' => $amount,
            'status' => $paymentStatus,
        ]);

        return $order;
    }

    public function test_open_creates_an_open_shift(): void
    {
        $user = User::factory()->create();

        $shift = (new ShiftService())->open($user->id, 150000);

        $this->assertSame($user->id, $shift->user_id);
        $this->assertSame(150000.0, (float) $shift->opening_cash);
        $this->assertSame(ShiftStatus::Open, $shift->status);
        $this->assertNotNull($shift->opened_at);
    }

    public function test_open_fails_when_user_already_has_an_open_shift(): void
    {
        $user = User::factory()->create();
        (new ShiftService())->open($user->id, 100000);

        $this->expectException(\Exception::class);

        (new ShiftService())->open($user->id, 50000);
    }

    public function test_close_computes_expected_cash_from_cash_sales_and_movements_only(): void
    {
        $user = User::factory()->create();
        $shift = (new ShiftService())->open($user->id, 100000);

        $this->makeOrderWithPayment($shift->id, PaymentMethod::Cash->value, PaymentStatus::Success->value, 60000);
        $this->makeOrderWithPayment($shift->id, PaymentMethod::Qris->value, PaymentStatus::Success->value, 40000);
        $this->makeOrderWithPayment($shift->id, PaymentMethod::Cash->value, PaymentStatus::Pending->value, 999999);

        CashMovement::create([
            'shift_id' => $shift->id,
            'type' => CashMovementType::In,
            'amount' => 20000,
            'reason' => 'Tambah modal',
            'created_by' => $user->id,
        ]);
        CashMovement::create([
            'shift_id' => $shift->id,
            'type' => CashMovementType::Out,
            'amount' => 5000,
            'reason' => 'Setor ke bank',
            'created_by' => $user->id,
        ]);

        $closed = (new ShiftService())->close($shift, 175500, 'Selisih Rp 500 karena kembalian kurang.');

        // expected = opening 100000 + cash sales 60000 + cash in 20000 - cash out 5000 = 175000
        $this->assertSame(175000.0, (float) $closed->expected_cash);
        $this->assertSame(175500.0, (float) $closed->actual_cash);
        $this->assertSame(500.0, (float) $closed->difference);
        $this->assertSame(ShiftStatus::Closed, $closed->status);
        $this->assertNotNull($closed->closed_at);
        $this->assertSame('Selisih Rp 500 karena kembalian kurang.', $closed->note);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ShiftServiceTest`
Expected: FAIL (class `App\Services\Shift\ShiftService` not found)

- [ ] **Step 3: Write `ShiftService`**

`app/Services/Shift/ShiftService.php`:

```php
<?php

namespace App\Services\Shift;

use App\Enum\Orders\PaymentStatus;
use App\Enum\Payments\PaymentMethod;
use App\Enum\Shifts\CashMovementType;
use App\Enum\Shifts\ShiftStatus;
use App\Models\Payment;
use App\Models\Shift;

class ShiftService
{
    public function open(int $userId, float $openingCash): Shift
    {
        $hasOpenShift = Shift::where('user_id', $userId)
            ->where('status', ShiftStatus::Open)
            ->exists();

        if ($hasOpenShift) {
            throw new \Exception('Anda masih punya shift yang belum ditutup.');
        }

        return Shift::create([
            'user_id' => $userId,
            'opening_cash' => $openingCash,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);
    }

    public function previewExpectedCash(Shift $shift): float
    {
        $cashSales = Payment::whereHas('order', fn ($q) => $q->where('shift_id', $shift->id))
            ->where('payment_method', PaymentMethod::Cash->value)
            ->where('status', PaymentStatus::Success->value)
            ->sum('amount');

        $cashIn = $shift->cashMovements()->where('type', CashMovementType::In->value)->sum('amount');
        $cashOut = $shift->cashMovements()->where('type', CashMovementType::Out->value)->sum('amount');

        return (float) $shift->opening_cash + (float) $cashSales + (float) $cashIn - (float) $cashOut;
    }

    public function close(Shift $shift, float $actualCash, ?string $note = null): Shift
    {
        $expectedCash = $this->previewExpectedCash($shift);
        $difference = $actualCash - $expectedCash;

        $shift->update([
            'expected_cash' => $expectedCash,
            'actual_cash' => $actualCash,
            'difference' => $difference,
            'note' => $note,
            'status' => ShiftStatus::Closed,
            'closed_at' => now(),
        ]);

        return $shift->fresh();
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=ShiftServiceTest`
Expected: PASS (3/3)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Shift tests/Feature/Services/ShiftServiceTest.php
git commit -m "feat: add ShiftService open/close with expected-cash reconciliation"
```

---

### Task 3: Cashier blocking gate — buka shift

**Files:**
- Modify: `app/Livewire/Pos/Cashier.php:1-64` (imports, properties, `boot()`, `mount()`), append `openShift()` method near other lifecycle/action methods
- Modify: `resources/views/livewire/pos/cashier.blade.php:1-16` (header) and `:210-217` (wrap body, add blocking screen)
- Modify: `resources/css/cashier.css` (append shift-gate + header badge styles)
- Test: `tests/Feature/Livewire/Pos/CashierShiftTest.php`

**Interfaces:**
- Consumes: `App\Services\Shift\ShiftService::open(int, float): Shift` (Task 2), `App\Models\Shift`, `App\Enum\Shifts\ShiftStatus` (Task 1).
- Produces: `public ?Shift $activeShift` on `Cashier` — Tasks 4, 5, 6 all read this property. `public string $shiftOpeningCash` and `openShift(): void` — this task's own surface, not consumed elsewhere.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Livewire/Pos/CashierShiftTest.php`:

```php
<?php

namespace Tests\Feature\Livewire\Pos;

use App\Enum\Shifts\ShiftStatus;
use App\Livewire\Pos\Cashier;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashierShiftTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(string $name, float $price): Product
    {
        $category = Category::firstOrCreate(['slug' => 'food'], ['name' => 'Food']);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'price' => $price,
            'has_recipe' => false,
            'stock' => 10,
            'is_out_of_stock' => false,
            'destination' => 'kitchen',
            'is_active' => true,
        ]);
    }

    public function test_renders_blocking_screen_when_no_active_shift(): void
    {
        $this->actingAs(User::factory()->create());
        $this->createProduct('Nasi Goreng', 20000);

        Livewire::test(Cashier::class)
            ->assertSee('Buka Shift')
            ->assertDontSee('Nasi Goreng');
    }

    public function test_renders_pos_normally_when_shift_active(): void
    {
        $user = User::factory()->create();
        Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 100000,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);
        $this->actingAs($user);
        $this->createProduct('Nasi Goreng', 20000);

        Livewire::test(Cashier::class)
            ->assertSee('Nasi Goreng')
            ->assertDontSee('Buka Shift');
    }

    public function test_opening_a_shift_creates_it_and_reveals_the_pos(): void
    {
        $this->actingAs(User::factory()->create());
        $this->createProduct('Nasi Goreng', 20000);

        Livewire::test(Cashier::class)
            ->set('shiftOpeningCash', '150000')
            ->call('openShift')
            ->assertSee('Nasi Goreng')
            ->assertDontSee('Buka Shift');

        $this->assertSame(1, Shift::count());
        $this->assertSame(150000.0, (float) Shift::first()->opening_cash);
    }

    public function test_opening_a_shift_fails_when_one_is_already_open(): void
    {
        $user = User::factory()->create();
        Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 100000,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);
        $this->actingAs($user);

        Livewire::test(Cashier::class)
            ->set('shiftOpeningCash', '50000')
            ->call('openShift')
            ->assertHasErrors('shiftOpeningCash');

        $this->assertSame(1, Shift::count());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CashierShiftTest`
Expected: FAIL (`activeShift`/`openShift` don't exist yet, blocking screen not rendered)

- [ ] **Step 3: Update `Cashier.php` imports and properties**

Add imports after the existing `use App\Traits\PaymentMethodSelection;` line (line 18 area):

```php
use App\Enum\Shifts\ShiftStatus;
use App\Models\Shift;
use App\Services\Shift\ShiftService;
```

Add the property near `public ?int $currentOrderId = null;` (line 36 area):

```php
    public ?Shift $activeShift = null;
    public string $shiftOpeningCash = '';
```

- [ ] **Step 4: Update `boot()` and `mount()`**

Replace lines 54-64:

```php
    public function boot(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function mount()
    {
        $this->categories = Category::all();
        $this->loadProducts();
        $this->ensureActivePaymentMethod();
    }
```

with:

```php
    protected ShiftService $shiftService;

    public function boot(OrderService $orderService, ShiftService $shiftService)
    {
        $this->orderService = $orderService;
        $this->shiftService = $shiftService;
    }

    public function mount()
    {
        $this->categories = Category::all();
        $this->loadProducts();
        $this->ensureActivePaymentMethod();
        $this->activeShift = Shift::where('user_id', Auth::id())->where('status', ShiftStatus::Open)->first();
    }

    public function openShift(): void
    {
        $this->validate([
            'shiftOpeningCash' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $this->activeShift = $this->shiftService->open(Auth::id(), (float) $this->shiftOpeningCash);
        } catch (\Exception $e) {
            $this->addError('shiftOpeningCash', $e->getMessage());
            return;
        }

        $this->shiftOpeningCash = '';
    }
```

- [ ] **Step 5: Wrap the Cashier view with the shift gate**

In `resources/views/livewire/pos/cashier.blade.php`, replace (lines 1-3):

```blade
<div class="pos-layout">
    <!-- Top Header -->
    <header class="pos-header">
```

with:

```blade
<div class="pos-layout">
@if($activeShift)
    <!-- Top Header -->
    <header class="pos-header">
```

Add a small "Shift Aktif" badge inside `.header-user`, replacing (lines 11-15):

```blade
        <div class="header-user">
            <div class="user-avatar">
                {{ $this->userInitials }}
            </div>
        </div>
    </header>
```

with:

```blade
        <div class="header-user">
            <div class="shift-badge">
                <span class="shift-badge-dot"></span>
                <span>Shift Aktif &middot; Rp {{ number_format($activeShift->opening_cash, 0, ',', '.') }} &middot; {{ $activeShift->opened_at->format('H:i') }}</span>
            </div>
            <div class="user-avatar">
                {{ $this->userInitials }}
            </div>
        </div>
    </header>
```

Replace the closing block (lines 210-217):

```blade
        <div class="qris-preview-overlay @if(!$showQrisPreviewModal) closed @endif">
            @if($showQrisPreviewModal)
                @include('livewire.pos.qris_preview_modal')
            @endif
        </div>
    </div>
</div>
```

with:

```blade
        <div class="qris-preview-overlay @if(!$showQrisPreviewModal) closed @endif">
            @if($showQrisPreviewModal)
                @include('livewire.pos.qris_preview_modal')
            @endif
        </div>
    </div>
@else
    <div class="shift-gate">
        <div class="shift-gate-card">
            <div class="shift-gate-icon"><i class="bi bi-safe2-fill"></i></div>
            <h2>Buka Shift</h2>
            <p>Masukkan modal awal kas sebelum mulai melayani transaksi.</p>

            @error('shiftOpeningCash')
                <div class="shift-gate-error">{{ $message }}</div>
            @enderror

            <form wire:submit.prevent="openShift" class="shift-gate-form">
                <label for="shiftOpeningCash">Modal Awal (Rp)</label>
                <input type="number" step="0.01" min="0" id="shiftOpeningCash" wire:model="shiftOpeningCash" class="shift-gate-input" placeholder="0" autofocus>
                <button type="submit" class="checkout-btn shift-gate-submit">
                    <i class="bi bi-unlock-fill"></i>
                    <span>Buka Shift</span>
                </button>
            </form>
        </div>
    </div>
@endif
</div>
```

- [ ] **Step 6: Add CSS**

Append to `resources/css/cashier.css`:

```css
.shift-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 500;
    color: var(--color-text-muted);
    background-color: var(--color-bg-subtle);
    border: 1px solid var(--color-border);
    border-radius: 999px;
    padding: 6px 12px;
    margin-right: 10px;
}

.shift-badge-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background-color: var(--color-success);
    flex-shrink: 0;
}

.shift-gate {
    width: 100%;
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: var(--color-bg-body);
}

.shift-gate-card {
    width: 100%;
    max-width: 380px;
    background-color: var(--color-bg-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg, 12px);
    box-shadow: var(--shadow-lg);
    padding: 32px 28px;
    text-align: center;
}

.shift-gate-icon {
    font-size: 32px;
    color: var(--color-primary);
    margin-bottom: 12px;
}

.shift-gate-card h2 {
    margin: 0 0 6px;
    font-size: 20px;
    font-weight: 700;
    color: var(--color-text-dark);
}

.shift-gate-card p {
    margin: 0 0 20px;
    font-size: 13px;
    color: var(--color-text-muted);
}

.shift-gate-error {
    background-color: var(--color-danger-bg);
    color: var(--color-danger-text);
    font-size: 12px;
    padding: 8px 12px;
    border-radius: var(--radius-sm);
    margin-bottom: 14px;
    text-align: left;
}

.shift-gate-form {
    display: flex;
    flex-direction: column;
    gap: 8px;
    text-align: left;
}

.shift-gate-form label {
    font-size: 12px;
    font-weight: 600;
    color: var(--color-text-secondary);
}

.shift-gate-input {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 12px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    font-size: 15px;
    color: var(--color-text-main);
}

.shift-gate-input:focus {
    outline: none;
    border-color: var(--color-primary);
}

.shift-gate-submit {
    width: 100%;
    justify-content: center;
    margin-top: 8px;
}
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=CashierShiftTest`
Expected: PASS (4/4)

- [ ] **Step 8: Commit**

```bash
git add app/Livewire/Pos/Cashier.php resources/views/livewire/pos/cashier.blade.php resources/css/cashier.css tests/Feature/Livewire/Pos/CashierShiftTest.php
git commit -m "feat: block Cashier behind an open-shift gate"
```

---

### Task 4: Orders created during a shift get `shift_id`

**Files:**
- Modify: `app/Services/Order/OrderService.php:20` (`processOrder()` signature), `:39-49` (create branch)
- Modify: `app/Livewire/Pos/Cashier.php:134` (`saveDraft()`), `:325` (`checkout()`), `:341-347` (`checkoutSplit()`)
- Test: `tests/Feature/Livewire/Pos/CashierShiftTest.php` (append)

**Interfaces:**
- Consumes: `Cashier::$activeShift` (Task 3).
- Produces: `OrderService::processOrder(..., ?int $shiftId = null)` — no other task calls this directly, but it must keep its existing positional-argument call sites working (see Global Constraints on not breaking existing behavior).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Livewire/Pos/CashierShiftTest.php` (inside the `CashierShiftTest` class, after the last test):

```php
    public function test_checkout_stamps_the_created_order_with_the_active_shift(): void
    {
        $user = User::factory()->create();
        $shift = Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 100000,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);
        $this->actingAs($user);
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->set('customerName', 'Andi')
            ->call('checkout');

        $orderId = $component->get('currentOrderId');

        $this->assertSame($shift->id, \App\Models\Order::find($orderId)->shift_id);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CashierShiftTest::test_checkout_stamps_the_created_order_with_the_active_shift`
Expected: FAIL (`shift_id` is `null`)

- [ ] **Step 3: Update `OrderService::processOrder()`**

In `app/Services/Order/OrderService.php`, replace the signature (line 20):

```php
    public function processOrder(array $cartItems, ?int $tableId = null, ?string $customerName = null, ?string $orderType = null, ?int $activeDraft = null, string $paymentMethod = 'cash'): Order
```

with:

```php
    public function processOrder(array $cartItems, ?int $tableId = null, ?string $customerName = null, ?string $orderType = null, ?int $activeDraft = null, string $paymentMethod = 'cash', ?int $shiftId = null): Order
```

Replace the create branch (lines 40-49):

```php
            $order = DB::transaction(function () use ($data, $tableId) {
                $data['table_id'] = $tableId;
                $data['customer_id'] = null;
                $data['status'] = OrderStatus::Pending;
                $data['payment_id'] = null;
                $data['order_number'] = $this->createOrderNumber();
                $data['user_id'] = Auth::id();

                return Order::create($data);
            });
```

with:

```php
            $order = DB::transaction(function () use ($data, $tableId, $shiftId) {
                $data['table_id'] = $tableId;
                $data['customer_id'] = null;
                $data['status'] = OrderStatus::Pending;
                $data['payment_id'] = null;
                $data['order_number'] = $this->createOrderNumber();
                $data['user_id'] = Auth::id();
                $data['shift_id'] = $shiftId;

                return Order::create($data);
            });
```

- [ ] **Step 4: Pass the active shift from `Cashier`**

In `app/Livewire/Pos/Cashier.php`, replace line 134 (`saveDraft()`):

```php
        $this->orderService->processOrder($this->cart, null, $this->customerName, $this->orderType, $this->activeDraft);
```

with:

```php
        $this->orderService->processOrder($this->cart, null, $this->customerName, $this->orderType, $this->activeDraft, shiftId: $this->activeShift?->id);
```

Replace line 325 (`checkout()`):

```php
        $order = $this->orderService->processOrder($this->cart, null, $this->customerName, $this->orderType, $this->activeDraft);
```

with:

```php
        $order = $this->orderService->processOrder($this->cart, null, $this->customerName, $this->orderType, $this->activeDraft, shiftId: $this->activeShift?->id);
```

Replace lines 341-347 (`checkoutSplit()`):

```php
                $order = $this->orderService->processOrder(
                    $this->buildSplitCartItems($index),
                    null,
                    $group['name'],
                    $this->orderType,
                    null
                );
```

with:

```php
                $order = $this->orderService->processOrder(
                    $this->buildSplitCartItems($index),
                    null,
                    $group['name'],
                    $this->orderType,
                    null,
                    shiftId: $this->activeShift?->id
                );
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=CashierShiftTest`
Expected: PASS (5/5)

Then run the full split-bill and payment-method suites to confirm the new optional parameter didn't break existing call sites:

Run: `php artisan test --filter=CashierSplitBillTest`
Run: `php artisan test --filter=CashierPaymentMethodTest`
Expected: both PASS, unchanged

- [ ] **Step 6: Commit**

```bash
git add app/Services/Order/OrderService.php app/Livewire/Pos/Cashier.php tests/Feature/Livewire/Pos/CashierShiftTest.php
git commit -m "feat: stamp orders created during a shift with shift_id"
```

---

### Task 5: Cash in/out modal

**Files:**
- Modify: `app/Livewire/Pos/Cashier.php` (properties near `$showQrisPreviewModal`, methods near `openQrisPreviewModal()`/`closeQrisPreviewModal()`)
- Create: `resources/views/livewire/pos/cash_movement_modal.blade.php`
- Modify: `resources/views/livewire/pos/cashier.blade.php` (add trigger button + modal overlay include)
- Modify: `resources/css/cashier.css` (append)
- Test: `tests/Feature/Livewire/Pos/CashierCashMovementTest.php`

**Interfaces:**
- Consumes: `Cashier::$activeShift` (Task 3), `App\Models\CashMovement`, `App\Enum\Shifts\CashMovementType` (Task 1).
- Produces: nothing consumed by later tasks (Task 6/2's `ShiftService::close()` reads `CashMovement` rows directly from the DB, not from this task's Livewire state).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Livewire/Pos/CashierCashMovementTest.php`:

```php
<?php

namespace Tests\Feature\Livewire\Pos;

use App\Enum\Shifts\CashMovementType;
use App\Enum\Shifts\ShiftStatus;
use App\Livewire\Pos\Cashier;
use App\Models\CashMovement;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashierCashMovementTest extends TestCase
{
    use RefreshDatabase;

    private function openShiftFor(User $user): Shift
    {
        return Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 100000,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);
    }

    public function test_recording_a_cash_in_movement(): void
    {
        $user = User::factory()->create();
        $shift = $this->openShiftFor($user);
        $this->actingAs($user);

        Livewire::test(Cashier::class)
            ->call('openCashMovementModal')
            ->assertSet('showCashMovementModal', true)
            ->set('cashMovementType', 'in')
            ->set('cashMovementAmount', '25000')
            ->set('cashMovementReason', 'Tambah modal')
            ->call('recordCashMovement')
            ->assertSet('showCashMovementModal', false);

        $movement = CashMovement::first();
        $this->assertSame($shift->id, $movement->shift_id);
        $this->assertSame(CashMovementType::In, $movement->type);
        $this->assertSame(25000.0, (float) $movement->amount);
        $this->assertSame('Tambah modal', $movement->reason);
        $this->assertSame($user->id, $movement->created_by);
    }

    public function test_recording_a_cash_out_movement(): void
    {
        $user = User::factory()->create();
        $this->openShiftFor($user);
        $this->actingAs($user);

        Livewire::test(Cashier::class)
            ->set('cashMovementType', 'out')
            ->set('cashMovementAmount', '15000')
            ->set('cashMovementReason', 'Setor ke bank')
            ->call('recordCashMovement');

        $this->assertSame(CashMovementType::Out, CashMovement::first()->type);
    }

    public function test_amount_and_reason_are_required(): void
    {
        $user = User::factory()->create();
        $this->openShiftFor($user);
        $this->actingAs($user);

        Livewire::test(Cashier::class)
            ->set('cashMovementType', 'in')
            ->set('cashMovementAmount', '0')
            ->set('cashMovementReason', '')
            ->call('recordCashMovement')
            ->assertHasErrors(['cashMovementAmount', 'cashMovementReason']);

        $this->assertSame(0, CashMovement::count());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CashierCashMovementTest`
Expected: FAIL (`openCashMovementModal`/`recordCashMovement` don't exist)

- [ ] **Step 3: Add properties and methods to `Cashier.php`**

Add properties near `public $showQrisPreviewModal = false;` (line 46 area):

```php
    public $showCashMovementModal = false;
    public string $cashMovementType = 'in';
    public string $cashMovementAmount = '';
    public string $cashMovementReason = '';
```

Add methods near `openQrisPreviewModal()`/`closeQrisPreviewModal()`:

```php
    public function openCashMovementModal(): void
    {
        $this->reset(['cashMovementType', 'cashMovementAmount', 'cashMovementReason']);
        $this->showCashMovementModal = true;
    }

    public function closeCashMovementModal(): void
    {
        $this->showCashMovementModal = false;
    }

    public function recordCashMovement(): void
    {
        $this->validate([
            'cashMovementType' => ['required', 'in:in,out'],
            'cashMovementAmount' => ['required', 'numeric', 'gt:0'],
            'cashMovementReason' => ['required', 'string', 'min:1'],
        ]);

        CashMovement::create([
            'shift_id' => $this->activeShift->id,
            'type' => $this->cashMovementType,
            'amount' => (float) $this->cashMovementAmount,
            'reason' => $this->cashMovementReason,
            'created_by' => Auth::id(),
        ]);

        $this->showCashMovementModal = false;
    }
```

Add the import next to the other `App\Models` imports (near `use App\Models\Order;`):

```php
use App\Models\CashMovement;
```

- [ ] **Step 4: Add the modal view**

Create `resources/views/livewire/pos/cash_movement_modal.blade.php`:

```blade
<div class="qris-preview-card">
    <div class="drafts-modal-header">
        <h3>Cash In / Out</h3>
        <button type="button" wire:click="closeCashMovementModal" class="close-desktop-btn">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <form wire:submit.prevent="recordCashMovement" class="cash-movement-form">
        <div class="cash-movement-type-toggle">
            <button type="button" wire:click="$set('cashMovementType', 'in')" class="cash-movement-type-btn @if($cashMovementType === 'in') active-in @endif">
                <i class="bi bi-box-arrow-in-down"></i> Cash In
            </button>
            <button type="button" wire:click="$set('cashMovementType', 'out')" class="cash-movement-type-btn @if($cashMovementType === 'out') active-out @endif">
                <i class="bi bi-box-arrow-up"></i> Cash Out
            </button>
        </div>

        <label for="cashMovementAmount">Nominal (Rp)</label>
        <input type="number" step="0.01" min="0" id="cashMovementAmount" wire:model="cashMovementAmount" class="shift-gate-input" placeholder="0">
        @error('cashMovementAmount') <div class="shift-gate-error">{{ $message }}</div> @enderror

        <label for="cashMovementReason">Alasan</label>
        <textarea id="cashMovementReason" wire:model="cashMovementReason" class="shift-gate-input cash-movement-reason" rows="2" placeholder="mis. setor ke bank, tambah modal..."></textarea>
        @error('cashMovementReason') <div class="shift-gate-error">{{ $message }}</div> @enderror

        <button type="submit" class="checkout-btn shift-gate-submit">
            <i class="bi bi-check-lg"></i>
            <span>Simpan</span>
        </button>
    </form>
</div>
```

- [ ] **Step 5: Wire the trigger button and overlay into `cashier.blade.php`**

Add a header button next to the shift badge — replace (the block added in Task 3, Step 5):

```blade
        <div class="header-user">
            <div class="shift-badge">
                <span class="shift-badge-dot"></span>
                <span>Shift Aktif &middot; Rp {{ number_format($activeShift->opening_cash, 0, ',', '.') }} &middot; {{ $activeShift->opened_at->format('H:i') }}</span>
            </div>
            <div class="user-avatar">
                {{ $this->userInitials }}
            </div>
        </div>
    </header>
```

with:

```blade
        <div class="header-user">
            <div class="shift-badge">
                <span class="shift-badge-dot"></span>
                <span>Shift Aktif &middot; Rp {{ number_format($activeShift->opening_cash, 0, ',', '.') }} &middot; {{ $activeShift->opened_at->format('H:i') }}</span>
            </div>
            <button type="button" wire:click="openCashMovementModal" class="secondary-btn shift-header-btn">
                <i class="bi bi-cash-coin"></i>
                <span>Cash In/Out</span>
            </button>
            <div class="user-avatar">
                {{ $this->userInitials }}
            </div>
        </div>
    </header>
```

Add the overlay include right after the qris-preview-overlay block (inserted in Task 3, Step 5, right before `@else`):

```blade
        <div class="qris-preview-overlay @if(!$showQrisPreviewModal) closed @endif">
            @if($showQrisPreviewModal)
                @include('livewire.pos.qris_preview_modal')
            @endif
        </div>

        <div class="qris-preview-overlay @if(!$showCashMovementModal) closed @endif">
            @if($showCashMovementModal)
                @include('livewire.pos.cash_movement_modal')
            @endif
        </div>
    </div>
@else
```

- [ ] **Step 6: Add CSS**

Append to `resources/css/cashier.css`:

```css
.shift-header-btn {
    padding: 8px 14px;
    font-size: 13px;
}

.cash-movement-form {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 20px;
}

.cash-movement-type-toggle {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
}

.cash-movement-type-btn {
    flex: 1;
    padding: 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background-color: var(--color-bg-surface);
    color: var(--color-text-muted);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.cash-movement-type-btn.active-in {
    border-color: var(--color-success);
    color: var(--color-success-text);
    background-color: var(--color-success-bg);
}

.cash-movement-type-btn.active-out {
    border-color: var(--color-danger);
    color: var(--color-danger-text);
    background-color: var(--color-danger-bg);
}

.cash-movement-reason {
    resize: none;
}
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=CashierCashMovementTest`
Expected: PASS (3/3)

- [ ] **Step 8: Commit**

```bash
git add app/Livewire/Pos/Cashier.php resources/views/livewire/pos/cash_movement_modal.blade.php resources/views/livewire/pos/cashier.blade.php resources/css/cashier.css tests/Feature/Livewire/Pos/CashierCashMovementTest.php
git commit -m "feat: add cash in/out recording to the Cashier shift"
```

---

### Task 6: Tutup shift

**Files:**
- Modify: `app/Livewire/Pos/Cashier.php` (properties, methods)
- Create: `resources/views/livewire/pos/end_shift_modal.blade.php`
- Modify: `resources/views/livewire/pos/cashier.blade.php` (trigger button + overlay)
- Modify: `resources/css/cashier.css` (append)
- Test: `tests/Feature/Livewire/Pos/CashierEndShiftTest.php`

**Interfaces:**
- Consumes: `Cashier::$activeShift` (Task 3), `ShiftService::close()` (Task 2).
- Produces: nothing consumed by later tasks — closing a shift just resets `$activeShift` to `null`, which re-triggers the Task 3 blocking gate on next render.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Livewire/Pos/CashierEndShiftTest.php`:

```php
<?php

namespace Tests\Feature\Livewire\Pos;

use App\Enum\Shifts\ShiftStatus;
use App\Livewire\Pos\Cashier;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashierEndShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_expected_cash_is_shown_when_opening_the_end_shift_modal(): void
    {
        $user = User::factory()->create();
        Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 100000,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);
        $this->actingAs($user);

        Livewire::test(Cashier::class)
            ->call('openEndShiftModal')
            ->assertSet('showEndShiftModal', true)
            ->assertSet('endShiftExpectedCash', 100000.0);
    }

    public function test_closing_the_shift_resets_active_shift_and_persists_the_reconciliation(): void
    {
        $user = User::factory()->create();
        $shift = Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 100000,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);
        $this->actingAs($user);

        Livewire::test(Cashier::class)
            ->call('openEndShiftModal')
            ->set('endShiftActualCash', '99500')
            ->set('endShiftNote', 'Kurang Rp 500')
            ->call('endShift')
            ->assertSet('activeShift', null)
            ->assertSee('Buka Shift');

        $shift->refresh();
        $this->assertSame(ShiftStatus::Closed, $shift->status);
        $this->assertSame(100000.0, (float) $shift->expected_cash);
        $this->assertSame(99500.0, (float) $shift->actual_cash);
        $this->assertSame(-500.0, (float) $shift->difference);
        $this->assertSame('Kurang Rp 500', $shift->note);
    }

    public function test_actual_cash_is_required_to_close(): void
    {
        $user = User::factory()->create();
        Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 100000,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);
        $this->actingAs($user);

        Livewire::test(Cashier::class)
            ->call('openEndShiftModal')
            ->set('endShiftActualCash', '')
            ->call('endShift')
            ->assertHasErrors('endShiftActualCash')
            ->assertSet('showEndShiftModal', true);

        $this->assertSame(1, Shift::where('status', ShiftStatus::Open->value)->count());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CashierEndShiftTest`
Expected: FAIL (`openEndShiftModal`/`endShift` don't exist)

- [ ] **Step 3: Add properties and methods to `Cashier.php`**

Add properties near `public $showCashMovementModal = false;` (added in Task 5):

```php
    public $showEndShiftModal = false;
    public ?float $endShiftExpectedCash = null;
    public string $endShiftActualCash = '';
    public string $endShiftNote = '';
```

Add methods near `closeCashMovementModal()`/`recordCashMovement()`:

```php
    public function openEndShiftModal(): void
    {
        $this->reset(['endShiftActualCash', 'endShiftNote']);
        $this->endShiftExpectedCash = $this->shiftService->previewExpectedCash($this->activeShift);
        $this->showEndShiftModal = true;
    }

    public function closeEndShiftModal(): void
    {
        $this->showEndShiftModal = false;
    }

    public function endShift(): void
    {
        $this->validate([
            'endShiftActualCash' => ['required', 'numeric', 'min:0'],
        ]);

        $this->shiftService->close($this->activeShift, (float) $this->endShiftActualCash, $this->endShiftNote ?: null);

        $this->activeShift = null;
        $this->showEndShiftModal = false;
    }
```

`openEndShiftModal()` calls `ShiftService::previewExpectedCash()` (Task 2) to show the figure before the shift is actually closed — no changes to `ShiftService` itself are needed in this task.

- [ ] **Step 4: Add the modal view**

Create `resources/views/livewire/pos/end_shift_modal.blade.php`:

```blade
<div class="qris-preview-card">
    <div class="drafts-modal-header">
        <h3>Tutup Shift</h3>
        <button type="button" wire:click="closeEndShiftModal" class="close-desktop-btn">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <form wire:submit.prevent="endShift" class="cash-movement-form">
        <label>Expected Cash (sistem)</label>
        <input type="text" class="shift-gate-input" value="Rp {{ number_format($endShiftExpectedCash ?? 0, 0, ',', '.') }}" disabled>

        <label for="endShiftActualCash">Actual Cash (hasil hitung fisik)</label>
        <input type="number" step="0.01" min="0" id="endShiftActualCash" wire:model="endShiftActualCash" class="shift-gate-input" placeholder="0">
        @error('endShiftActualCash') <div class="shift-gate-error">{{ $message }}</div> @enderror

        <label for="endShiftNote">Catatan (opsional)</label>
        <textarea id="endShiftNote" wire:model="endShiftNote" class="shift-gate-input cash-movement-reason" rows="2" placeholder="Catatan kalau ada selisih..."></textarea>

        <button type="submit" class="checkout-btn shift-gate-submit">
            <i class="bi bi-lock-fill"></i>
            <span>Tutup Shift</span>
        </button>
    </form>
</div>
```

- [ ] **Step 5: Wire the trigger button and overlay into `cashier.blade.php`**

Extend the header button block from Task 5 — replace:

```blade
            <button type="button" wire:click="openCashMovementModal" class="secondary-btn shift-header-btn">
                <i class="bi bi-cash-coin"></i>
                <span>Cash In/Out</span>
            </button>
            <div class="user-avatar">
```

with:

```blade
            <button type="button" wire:click="openCashMovementModal" class="secondary-btn shift-header-btn">
                <i class="bi bi-cash-coin"></i>
                <span>Cash In/Out</span>
            </button>
            <button type="button" wire:click="openEndShiftModal" class="secondary-btn shift-header-btn">
                <i class="bi bi-lock-fill"></i>
                <span>Tutup Shift</span>
            </button>
            <div class="user-avatar">
```

Extend the overlay block from Task 5 — replace:

```blade
        <div class="qris-preview-overlay @if(!$showCashMovementModal) closed @endif">
            @if($showCashMovementModal)
                @include('livewire.pos.cash_movement_modal')
            @endif
        </div>
    </div>
@else
```

with:

```blade
        <div class="qris-preview-overlay @if(!$showCashMovementModal) closed @endif">
            @if($showCashMovementModal)
                @include('livewire.pos.cash_movement_modal')
            @endif
        </div>

        <div class="qris-preview-overlay @if(!$showEndShiftModal) closed @endif">
            @if($showEndShiftModal)
                @include('livewire.pos.end_shift_modal')
            @endif
        </div>
    </div>
@else
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=CashierEndShiftTest`
Expected: PASS (3/3)

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Pos/Cashier.php resources/views/livewire/pos/end_shift_modal.blade.php resources/views/livewire/pos/cashier.blade.php resources/css/cashier.css tests/Feature/Livewire/Pos/CashierEndShiftTest.php
git commit -m "feat: add tutup shift reconciliation flow to the Cashier"
```

---

### Task 7: Filament `ShiftResource` (read-only) + `CashMovementsRelationManager`

**Files:**
- Create: `app/Filament/Resources/Shifts/ShiftResource.php`
- Create: `app/Filament/Resources/Shifts/Pages/ListShifts.php`
- Create: `app/Filament/Resources/Shifts/Pages/ViewShift.php`
- Create: `app/Filament/Resources/Shifts/Tables/ShiftsTable.php`
- Create: `app/Filament/Resources/Shifts/RelationManagers/CashMovementsRelationManager.php`
- Test: `tests/Feature/Filament/ShiftResourceTest.php`

**Interfaces:**
- Consumes: `Shift`, `CashMovement` models (Task 1).
- Produces: nothing consumed by later tasks (Task 8's `ShiftReport` queries `Shift` directly, not through this resource).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Filament/ShiftResourceTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Enum\Shifts\ShiftStatus;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_reachable(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/shifts')->assertSuccessful();
    }

    public function test_view_page_is_reachable(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $shift = Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 100000,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);

        $this->get("/admin/shifts/{$shift->id}")->assertSuccessful();
    }

    public function test_create_route_does_not_exist(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/shifts/create')->assertNotFound();
    }

    public function test_edit_route_does_not_exist(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $shift = Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 100000,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);

        $this->get("/admin/shifts/{$shift->id}/edit")->assertNotFound();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ShiftResourceTest`
Expected: FAIL (404 on `/admin/shifts` — resource doesn't exist)

- [ ] **Step 3: Write `ShiftResource.php`**

`app/Filament/Resources/Shifts/ShiftResource.php`:

```php
<?php

namespace App\Filament\Resources\Shifts;

use App\Filament\Resources\Shifts\Pages\ListShifts;
use App\Filament\Resources\Shifts\Pages\ViewShift;
use App\Filament\Resources\Shifts\RelationManagers\CashMovementsRelationManager;
use App\Filament\Resources\Shifts\Tables\ShiftsTable;
use App\Models\Shift;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Transaction';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('user.name')->label('Kasir'),
            TextEntry::make('opened_at')->dateTime(),
            TextEntry::make('closed_at')->dateTime()->placeholder('Masih berjalan'),
            TextEntry::make('opening_cash')->money('IDR'),
            TextEntry::make('expected_cash')->money('IDR')->placeholder('-'),
            TextEntry::make('actual_cash')->money('IDR')->placeholder('-'),
            TextEntry::make('difference')->money('IDR')->placeholder('-')
                ->color(fn (?string $state): string => match (true) {
                    $state === null => 'gray',
                    (float) $state === 0.0 => 'success',
                    default => 'danger',
                }),
            TextEntry::make('status')->badge(),
            TextEntry::make('note')->placeholder('-')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return ShiftsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CashMovementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShifts::route('/'),
            'view' => ViewShift::route('/{record}'),
        ];
    }
}
```

- [ ] **Step 4: Write the pages**

`app/Filament/Resources/Shifts/Pages/ListShifts.php`:

```php
<?php

namespace App\Filament\Resources\Shifts\Pages;

use App\Filament\Resources\Shifts\ShiftResource;
use Filament\Resources\Pages\ListRecords;

class ListShifts extends ListRecords
{
    protected static string $resource = ShiftResource::class;
}
```

`app/Filament/Resources/Shifts/Pages/ViewShift.php`:

```php
<?php

namespace App\Filament\Resources\Shifts\Pages;

use App\Filament\Resources\Shifts\ShiftResource;
use Filament\Resources\Pages\ViewRecord;

class ViewShift extends ViewRecord
{
    protected static string $resource = ShiftResource::class;
}
```

- [ ] **Step 5: Write the table**

`app/Filament/Resources/Shifts/Tables/ShiftsTable.php`:

```php
<?php

namespace App\Filament\Resources\Shifts\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShiftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Kasir')
                    ->searchable(),
                TextColumn::make('opened_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->dateTime()
                    ->placeholder('Masih berjalan')
                    ->sortable(),
                TextColumn::make('opening_cash')
                    ->money('IDR'),
                TextColumn::make('expected_cash')
                    ->money('IDR')
                    ->placeholder('-'),
                TextColumn::make('actual_cash')
                    ->money('IDR')
                    ->placeholder('-'),
                TextColumn::make('difference')
                    ->money('IDR')
                    ->placeholder('-')
                    ->color(fn (?string $state): string => match (true) {
                        $state === null => 'gray',
                        (float) $state === 0.0 => 'success',
                        default => 'danger',
                    }),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->defaultSort('opened_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
```

- [ ] **Step 6: Write the relation manager**

`app/Filament/Resources/Shifts/RelationManagers/CashMovementsRelationManager.php`:

```php
<?php

namespace App\Filament\Resources\Shifts\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CashMovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'cashMovements';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->columns([
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('amount')
                    ->money('IDR'),
                TextColumn::make('reason'),
                TextColumn::make('creator.name')
                    ->label('By'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=ShiftResourceTest`
Expected: PASS (4/4)

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Resources/Shifts tests/Feature/Filament/ShiftResourceTest.php
git commit -m "feat: add read-only Shift Filament resource with cash movements"
```

---

### Task 8: `ShiftReport` page

**Files:**
- Create: `app/Filament/Pages/Reports/ShiftReport.php`
- Create: `resources/views/filament/pages/reports/shift-report.blade.php`
- Create: `resources/views/pdf/reports/shift-report.blade.php`
- Test: `tests/Feature/Filament/ShiftReportPageTest.php`

**Interfaces:**
- Consumes: `Shift`, `CashMovement` models (Task 1), `HasReportPeriod` trait (`app/Filament/Pages/Reports/Concerns/HasReportPeriod.php`, pre-existing — read but do not modify), `App\Enum\Payments\PaymentMethod`, `App\Enum\Orders\PaymentStatus`.
- Produces: nothing consumed by other tasks — this is the final task.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Filament/ShiftReportPageTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Enum\Orders\OrderStatus;
use App\Enum\Shifts\CashMovementType;
use App\Enum\Shifts\ShiftStatus;
use App\Filament\Pages\Reports\ShiftReport;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShiftReportPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeShiftWithSales(User $user, float $opening, float $cashSales, float $nonCashSales): Shift
    {
        $shift = Shift::create([
            'user_id' => $user->id,
            'opening_cash' => $opening,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);

        $order = Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'total_amount' => $cashSales,
            'tax' => 0,
            'discount' => 0,
            'status' => OrderStatus::Completed,
            'order_type' => 'dine_in',
            'shift_id' => $shift->id,
        ]);
        Payment::create(['order_id' => $order->id, 'payment_method' => 'cash', 'amount' => $cashSales, 'status' => 'success']);

        $order2 = Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'total_amount' => $nonCashSales,
            'tax' => 0,
            'discount' => 0,
            'status' => OrderStatus::Completed,
            'order_type' => 'dine_in',
            'shift_id' => $shift->id,
        ]);
        Payment::create(['order_id' => $order2->id, 'payment_method' => 'qris', 'amount' => $nonCashSales, 'status' => 'success']);

        return $shift;
    }

    public function test_get_rows_reports_sales_movements_and_reconciliation(): void
    {
        $user = User::factory()->create(['name' => 'Andi']);
        $this->actingAs($user);
        $shift = $this->makeShiftWithSales($user, 100000, 60000, 40000);

        CashMovement::create(['shift_id' => $shift->id, 'type' => CashMovementType::In, 'amount' => 10000, 'reason' => 'x', 'created_by' => $user->id]);
        CashMovement::create(['shift_id' => $shift->id, 'type' => CashMovementType::Out, 'amount' => 5000, 'reason' => 'y', 'created_by' => $user->id]);

        $shift->update(['expected_cash' => 165000, 'actual_cash' => 165000, 'difference' => 0, 'status' => ShiftStatus::Closed, 'closed_at' => now()]);

        $rows = Livewire::test(ShiftReport::class)->instance()->getRows();

        $this->assertCount(1, $rows);
        $row = $rows->first();

        $this->assertSame('Andi', $row['user']);
        $this->assertEquals(100000.0, $row['opening_cash']);
        $this->assertEquals(60000.0, $row['cash_sales']);
        $this->assertEquals(40000.0, $row['non_cash_sales']);
        $this->assertEquals(10000.0, $row['cash_in']);
        $this->assertEquals(5000.0, $row['cash_out']);
        $this->assertEquals(165000.0, $row['expected_cash']);
        $this->assertEquals(165000.0, $row['actual_cash']);
        $this->assertEquals(0.0, $row['difference']);
    }

    public function test_user_id_filter_narrows_rows_to_one_cashier(): void
    {
        $andi = User::factory()->create(['name' => 'Andi']);
        $budi = User::factory()->create(['name' => 'Budi']);
        $this->actingAs($andi);

        $this->makeShiftWithSales($andi, 100000, 10000, 0);
        $this->makeShiftWithSales($budi, 50000, 20000, 0);

        $rows = Livewire::test(ShiftReport::class)
            ->set('data.user_id', $andi->id)
            ->instance()
            ->getRows();

        $this->assertCount(1, $rows);
        $this->assertSame('Andi', $rows->first()['user']);
    }

    public function test_csv_export_contains_header_and_data(): void
    {
        $user = User::factory()->create(['name' => 'Andi']);
        $this->actingAs($user);
        $this->makeShiftWithSales($user, 100000, 60000, 0);

        $test = Livewire::test(ShiftReport::class)->callAction('exportCsv');

        $test->assertFileDownloaded(contentType: 'text/csv');
        $content = base64_decode(data_get($test->effects, 'download.content'));

        $this->assertStringContainsString('Kasir', $content);
        $this->assertStringContainsString('Andi', $content);
        $this->assertStringContainsString('60000', $content);
    }

    public function test_pdf_export_returns_a_pdf(): void
    {
        $user = User::factory()->create(['name' => 'Andi']);
        $this->actingAs($user);
        $this->makeShiftWithSales($user, 100000, 60000, 0);

        $test = Livewire::test(ShiftReport::class)->callAction('exportPdf');

        $test->assertFileDownloaded(contentType: 'application/pdf');
        $content = base64_decode(data_get($test->effects, 'download.content'));

        $this->assertStringStartsWith('%PDF', $content);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ShiftReportPageTest`
Expected: FAIL (class `App\Filament\Pages\Reports\ShiftReport` not found)

- [ ] **Step 3: Write `ShiftReport.php`**

`app/Filament/Pages/Reports/ShiftReport.php`:

```php
<?php

namespace App\Filament\Pages\Reports;

use App\Enum\Orders\PaymentStatus;
use App\Enum\Payments\PaymentMethod;
use App\Enum\Shifts\CashMovementType;
use App\Filament\Pages\Reports\Concerns\HasReportPeriod;
use App\Models\Shift;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use League\Csv\EscapeFormula;
use League\Csv\Writer;
use UnitEnum;

class ShiftReport extends Page
{
    use HasReportPeriod;

    protected static ?string $title = 'Shift Report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.reports.shift-report';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['period_type' => 'daily']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                $this->periodTypeField(),
                $this->dateRangeField(),
                Select::make('user_id')
                    ->label('Kasir')
                    ->options(fn () => User::pluck('name', 'id'))
                    ->placeholder('Semua kasir')
                    ->live(),
            ]);
    }

    /** @return Collection<int, array{user: string, opened_at: string, closed_at: ?string, opening_cash: float, cash_sales: float, non_cash_sales: float, cash_in: float, cash_out: float, expected_cash: ?float, actual_cash: ?float, difference: ?float}> */
    public function getRows(): Collection
    {
        [$start, $end] = $this->dateRange();
        $userId = $this->data['user_id'] ?? null;

        return Shift::query()
            ->with(['user', 'orders.payment', 'cashMovements'])
            ->whereBetween('opened_at', [$start, $end])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->get()
            ->map(function (Shift $shift) {
                $payments = $shift->orders->pluck('payment')->filter(
                    fn ($payment) => $payment && $payment->status === PaymentStatus::Success
                );

                $cashSales = (float) $payments->where('payment_method', PaymentMethod::Cash->value)->sum('amount');
                $nonCashSales = (float) $payments->where('payment_method', '!=', PaymentMethod::Cash->value)->sum('amount');

                $cashIn = (float) $shift->cashMovements->where('type', CashMovementType::In)->sum('amount');
                $cashOut = (float) $shift->cashMovements->where('type', CashMovementType::Out)->sum('amount');

                return [
                    'user' => $shift->user->name,
                    'opened_at' => $shift->opened_at->format('Y-m-d H:i'),
                    'closed_at' => $shift->closed_at?->format('Y-m-d H:i'),
                    'opening_cash' => (float) $shift->opening_cash,
                    'cash_sales' => $cashSales,
                    'non_cash_sales' => $nonCashSales,
                    'cash_in' => $cashIn,
                    'cash_out' => $cashOut,
                    'expected_cash' => $shift->expected_cash !== null ? (float) $shift->expected_cash : null,
                    'actual_cash' => $shift->actual_cash !== null ? (float) $shift->actual_cash : null,
                    'difference' => $shift->difference !== null ? (float) $shift->difference : null,
                ];
            })
            ->sortByDesc('opened_at')
            ->values();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedTableCells)
                ->action(fn () => $this->exportCsv()),
            Action::make('exportPdf')
                ->label('Export PDF')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->action(fn () => $this->exportPdf()),
        ];
    }

    protected function exportCsv()
    {
        $rows = $this->getRows();

        $csv = Writer::createFromString('');
        $csv->addFormatter(new EscapeFormula());
        $csv->insertOne(['Kasir', 'Dibuka', 'Ditutup', 'Modal Awal', 'Cash Sales', 'Non-Cash Sales', 'Cash In', 'Cash Out', 'Expected', 'Actual', 'Selisih']);

        foreach ($rows as $row) {
            $csv->insertOne([
                $row['user'],
                $row['opened_at'],
                $row['closed_at'] ?? '-',
                $row['opening_cash'],
                $row['cash_sales'],
                $row['non_cash_sales'],
                $row['cash_in'],
                $row['cash_out'],
                $row['expected_cash'] ?? '-',
                $row['actual_cash'] ?? '-',
                $row['difference'] ?? '-',
            ]);
        }

        return response()->streamDownload(
            fn () => print $csv->toString(),
            'shift-report-' . now()->format('Ymd-His') . '.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    protected function exportPdf()
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reports.shift-report', [
            'rows' => $this->getRows(),
            'periodType' => $this->periodType(),
        ]);

        return response()->streamDownload(
            fn () => print $pdf->output(),
            'shift-report-' . now()->format('Ymd-His') . '.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
```

- [ ] **Step 4: Write the Filament page blade view**

`resources/views/filament/pages/reports/shift-report.blade.php`:

```blade
<x-filament-panels::page>
    <style>
        .report-table-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            overflow-x: auto;
        }
        .report-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .report-table thead { background: #f9fafb; }
        .report-table th {
            text-align: left;
            padding: 0.75rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }
        .report-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f3f4f6;
            color: #111827;
            white-space: nowrap;
        }
        .report-table tbody tr:last-child td { border-bottom: none; }
        .report-table tbody tr:hover { background: #f9fafb; }
        .report-table th.report-num, .report-table td.report-num { text-align: right; }
        .report-table .report-empty { text-align: center; padding: 2rem 1rem; color: #9ca3af; white-space: normal; }

        .dark .report-table-card { background: #18181b; border-color: #3f3f46; }
        .dark .report-table thead { background: #27272a; }
        .dark .report-table th { color: #a1a1aa; border-bottom-color: #3f3f46; }
        .dark .report-table td { color: #f4f4f5; border-bottom-color: #27272a; }
        .dark .report-table tbody tr:hover { background: #27272a; }
        .dark .report-table .report-empty { color: #71717a; }
    </style>

    <div class="mb-4">
        {{ $this->form }}
    </div>

    <div class="report-table-card">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Kasir</th>
                    <th>Dibuka</th>
                    <th>Ditutup</th>
                    <th class="report-num">Modal Awal</th>
                    <th class="report-num">Cash Sales</th>
                    <th class="report-num">Non-Cash Sales</th>
                    <th class="report-num">Cash In</th>
                    <th class="report-num">Cash Out</th>
                    <th class="report-num">Expected</th>
                    <th class="report-num">Actual</th>
                    <th class="report-num">Selisih</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->getRows() as $row)
                    <tr>
                        <td>{{ $row['user'] }}</td>
                        <td>{{ $row['opened_at'] }}</td>
                        <td>{{ $row['closed_at'] ?? '-' }}</td>
                        <td class="report-num">{{ number_format($row['opening_cash'], 2) }}</td>
                        <td class="report-num">{{ number_format($row['cash_sales'], 2) }}</td>
                        <td class="report-num">{{ number_format($row['non_cash_sales'], 2) }}</td>
                        <td class="report-num">{{ number_format($row['cash_in'], 2) }}</td>
                        <td class="report-num">{{ number_format($row['cash_out'], 2) }}</td>
                        <td class="report-num">{{ $row['expected_cash'] !== null ? number_format($row['expected_cash'], 2) : '-' }}</td>
                        <td class="report-num">{{ $row['actual_cash'] !== null ? number_format($row['actual_cash'], 2) : '-' }}</td>
                        <td class="report-num">{{ $row['difference'] !== null ? number_format($row['difference'], 2) : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="report-empty">No data for this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
```

- [ ] **Step 5: Write the PDF blade view**

`resources/views/pdf/reports/shift-report.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        h1 { font-size: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background-color: #f3f4f6; }
    </style>
</head>
<body>
    <h1>Shift Report ({{ ucfirst($periodType) }})</h1>
    <table>
        <thead>
            <tr>
                <th>Kasir</th>
                <th>Dibuka</th>
                <th>Ditutup</th>
                <th>Modal Awal</th>
                <th>Cash Sales</th>
                <th>Non-Cash Sales</th>
                <th>Cash In</th>
                <th>Cash Out</th>
                <th>Expected</th>
                <th>Actual</th>
                <th>Selisih</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['user'] }}</td>
                    <td>{{ $row['opened_at'] }}</td>
                    <td>{{ $row['closed_at'] ?? '-' }}</td>
                    <td>{{ number_format($row['opening_cash'], 2) }}</td>
                    <td>{{ number_format($row['cash_sales'], 2) }}</td>
                    <td>{{ number_format($row['non_cash_sales'], 2) }}</td>
                    <td>{{ number_format($row['cash_in'], 2) }}</td>
                    <td>{{ number_format($row['cash_out'], 2) }}</td>
                    <td>{{ $row['expected_cash'] !== null ? number_format($row['expected_cash'], 2) : '-' }}</td>
                    <td>{{ $row['actual_cash'] !== null ? number_format($row['actual_cash'], 2) : '-' }}</td>
                    <td>{{ $row['difference'] !== null ? number_format($row['difference'], 2) : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="11">No data for this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=ShiftReportPageTest`
Expected: PASS (4/4)

- [ ] **Step 7: Run the full test suite**

Run: `php artisan test`
Expected: PASS, no regressions in any other suite

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Pages/Reports/ShiftReport.php resources/views/filament/pages/reports/shift-report.blade.php resources/views/pdf/reports/shift-report.blade.php tests/Feature/Filament/ShiftReportPageTest.php
git commit -m "feat: add Shift Report page with CSV/PDF export"
```
