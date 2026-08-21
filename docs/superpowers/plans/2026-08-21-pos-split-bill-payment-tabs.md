# POS Split Bill Payment Tabs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** After a POS cashier checks out a split bill, the existing payment modal opens directly with one browser-style tab per split group, so the cashier pays each person without leaving the modal or visiting the Drafts list.

**Architecture:** `checkoutSplit()` still creates one `Order`+`Payment` per group up front (same as today — this is what keeps an abandoned split safely recoverable from Drafts), but instead of resetting state and redirecting, it opens the payment modal with `activeSplitIndex = 0`. A new `switchSplitTab()` method moves the payment form (order id, method, cash received) between groups. `finalizeOrder()` branches: in split mode it marks the active group paid and auto-advances to the next unpaid tab instead of closing the modal; only once every group is paid does it reset state, close the modal, and redirect to the receipt (the one place a full-page redirect is safe, since no other tab still needs Livewire state).

**Tech Stack:** Laravel, Livewire 3 (+ bundled Alpine), Blade, PHPUnit (`RefreshDatabase` + `Livewire::test()`).

## Global Constraints

- Scope is POS only: `app/Livewire/Pos/Cashier.php` and
  `resources/views/livewire/pos/payment_modal.blade.php` (+ CSS). Do not
  touch `app/Livewire/LandingPage/LandingPage.php` or its views.
- `checkoutSplit()` must keep creating N `Order`/`Payment` rows up front,
  `status=Pending` — never delay order creation to "pay time". This is the
  existing Drafts-list safety net and must not regress.
- Never let `finalizeOrder()` re-finalize a split tab that is already
  `paid` — guard it server-side, not just by disabling the button.
- QRIS amounts (`qrisImage`, `openQrisPreviewModal()`) must reflect the
  active split group's total while in split mode, not the whole cart —
  reuse the existing `qrisImageForAmount()`/`qrisImageForOrderAmount()`
  helpers already in `PaymentMethodSelection`, don't add new QR generation
  code.
- Follow existing code style: no doc blocks, no comments except where a
  non-obvious constraint needs explaining.
- Every new method that isn't a trivial one-liner getter gets a PHPUnit
  test through `Livewire::test(Cashier::class)`, appended to
  `tests/Feature/Livewire/Pos/CashierSplitBillTest.php` (existing file —
  keep its `createProduct()` helper, don't duplicate it).

---

### Task 1: `checkoutSplit()` opens the payment modal instead of redirecting to Drafts

**Files:**
- Modify: `app/Livewire/Pos/Cashier.php:318-343` (`checkoutSplit()`), plus add one property near `public $activeDraft = null;` (line 37)
- Modify: `tests/Feature/Livewire/Pos/CashierSplitBillTest.php:229-257` (`test_checkout_split_creates_one_order_per_group`) and `:322-350` (`test_splitting_a_loaded_draft_replaces_it_instead_of_duplicating_it`)

**Interfaces:**
- Consumes: `canCheckoutSplit()`, `buildSplitCartItems(int)`, `$splitGroups` (shape `['name' => string, 'assignments' => [productId => qty]]`), `OrderService::processOrder()` — all unchanged, from `CartCalculation` trait / `OrderService`.
- Produces: `public ?int $activeSplitIndex` (null = not in split-payment mode). After `checkoutSplit()`, each `$splitGroups[$i]` gains `order_id` (int) and `paid` (bool, `false`) keys — later tasks depend on both.

- [ ] **Step 1: Write the failing test**

Add this test to `tests/Feature/Livewire/Pos/CashierSplitBillTest.php` (after `test_checkout_split_does_nothing_when_not_ready`, i.e. after line 270):

```php
    public function test_checkout_split_opens_payment_modal_on_first_tab(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->assertSet('showPaymentModal', true)
            ->assertSet('activeSplitIndex', 0);

        $andi = \App\Models\Order::where('customer_name', 'Andi')->first();
        $budi = \App\Models\Order::where('customer_name', 'Budi')->first();

        $component->assertSet('currentOrderId', $andi->id);
        $this->assertSame($andi->id, $component->instance()->splitGroups[0]['order_id']);
        $this->assertFalse($component->instance()->splitGroups[0]['paid']);
        $this->assertSame($budi->id, $component->instance()->splitGroups[1]['order_id']);
        $this->assertFalse($component->instance()->splitGroups[1]['paid']);
    }
```

Then update the now-stale assertions in the two existing tests:

Replace in `test_checkout_split_creates_one_order_per_group` (currently lines 234-244):

```php
        Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->assertSet('cart', [])
            ->assertSet('splitMode', false)
            ->assertSet('splitGroups', []);
```

with:

```php
        Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->assertSet('showPaymentModal', true);
```

(cart/splitMode/splitGroups now stay populated until every tab is paid — that's covered by the new test above and by Task 3's tests, not this one.)

Replace in `test_splitting_a_loaded_draft_replaces_it_instead_of_duplicating_it` (currently lines 336-349):

```php
        Livewire::test(Cashier::class)
            ->call('loadDraft', $original->id)
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->assertSet('currentOrderId', null)
            ->assertSet('activeDraft', null);

        $this->assertSame(2, \App\Models\Order::count());
        $this->assertNull(\App\Models\Order::find($original->id));
        $this->assertNotNull(\App\Models\Order::where('customer_name', 'Andi')->first());
        $this->assertNotNull(\App\Models\Order::where('customer_name', 'Budi')->first());
    }
```

with:

```php
        $component = Livewire::test(Cashier::class)
            ->call('loadDraft', $original->id)
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->assertSet('activeDraft', null);

        $this->assertSame(2, \App\Models\Order::count());
        $this->assertNull(\App\Models\Order::find($original->id));
        $andi = \App\Models\Order::where('customer_name', 'Andi')->first();
        $this->assertNotNull($andi);
        $this->assertNotNull(\App\Models\Order::where('customer_name', 'Budi')->first());
        $component->assertSet('currentOrderId', $andi->id);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CashierSplitBillTest`
Expected: FAIL — `activeSplitIndex` undefined property, and the two updated assertions fail against the current (unmodified) `checkoutSplit()`.

- [ ] **Step 3: Implement in `app/Livewire/Pos/Cashier.php`**

Add the new property right after `public $activeDraft = null;` (line 37):

```php
    public ?int $activeSplitIndex = null;
```

Replace `checkoutSplit()` (lines 318-343):

```php
    public function checkoutSplit(): void
    {
        if (! $this->canCheckoutSplit()) return;

        if ($this->currentOrderId) {
            $this->deleteDraft($this->currentOrderId);
        }

        DB::transaction(function () {
            foreach ($this->splitGroups as $index => $group) {
                $order = $this->orderService->processOrder(
                    $this->buildSplitCartItems($index),
                    null,
                    $group['name'],
                    $this->orderType,
                    null
                );

                $this->splitGroups[$index]['order_id'] = $order->id;
                $this->splitGroups[$index]['paid'] = false;
            }
        });

        $this->activeDraft = null;
        $this->activeSplitIndex = 0;
        $this->currentOrderId = $this->splitGroups[0]['order_id'];
        $this->paymentMethod = 'cash';
        $this->paymentConfirmed = false;
        $this->cashReceived = null;
        $this->ensureActivePaymentMethod();
        $this->showPaymentModal = true;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=CashierSplitBillTest`
Expected: PASS (all tests in the file, including the new one).

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/Pos/Cashier.php tests/Feature/Livewire/Pos/CashierSplitBillTest.php
git commit -m "feat: open payment modal on split checkout instead of redirecting to drafts"
```

---

### Task 2: `switchSplitTab()` + per-group tax/total helpers

**Files:**
- Modify: `app/Livewire/Pos/Cashier.php` (add methods after `checkoutSplit()`)
- Modify: `tests/Feature/Livewire/Pos/CashierSplitBillTest.php` (append tests)

**Interfaces:**
- Consumes: Task 1's `$activeSplitIndex`, `$splitGroups[*]['order_id']`; `CartCalculation::splitGroupSubtotal(int): float`; `$this->cartCalculatorService` (already available via the `CartCalculation` trait's `bootCartCalculation()`).
- Produces: `switchSplitTab(int $index): void`, `splitGroupTax(int $index): float`, `splitGroupTotal(int $index): float` — Task 3 and Task 5 depend on all three.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Livewire/Pos/CashierSplitBillTest.php`:

```php
    public function test_switch_split_tab_changes_active_order_and_resets_payment_fields(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->set('paymentMethod', 'qris')
            ->set('paymentConfirmed', true)
            ->set('cashReceived', 20000);

        $budiOrderId = $component->instance()->splitGroups[1]['order_id'];

        $component->call('switchSplitTab', 1)
            ->assertSet('activeSplitIndex', 1)
            ->assertSet('currentOrderId', $budiOrderId)
            ->assertSet('paymentMethod', 'cash')
            ->assertSet('paymentConfirmed', false)
            ->assertSet('cashReceived', null);
    }

    public function test_switch_split_tab_ignores_invalid_index(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->call('switchSplitTab', 5)
            ->assertSet('activeSplitIndex', 0);
    }

    public function test_split_group_tax_and_total_match_group_subtotal(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id);

        $this->assertSame(2200.0, $component->instance()->splitGroupTax(0));
        $this->assertSame(22200.0, $component->instance()->splitGroupTotal(0));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CashierSplitBillTest`
Expected: FAIL — `switchSplitTab`/`splitGroupTax`/`splitGroupTotal` not found.

- [ ] **Step 3: Implement in `app/Livewire/Pos/Cashier.php`**

Add after `checkoutSplit()`:

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

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=CashierSplitBillTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/Pos/Cashier.php tests/Feature/Livewire/Pos/CashierSplitBillTest.php
git commit -m "feat: add split-tab switching and per-group totals to Cashier"
```

---

### Task 3: `finalizeOrder()` branches on split mode — pay, advance, or close out

**Files:**
- Modify: `app/Livewire/Pos/Cashier.php:345-357` (`finalizeOrder()`)
- Modify: `tests/Feature/Livewire/Pos/CashierSplitBillTest.php` (append tests)

**Interfaces:**
- Consumes: Task 1/2's `$activeSplitIndex`, `$splitGroups[*]['order_id']`/`['paid']`, `switchSplitTab(int)`; existing `OrderService::finalizeOrder()`, `resetCashier()`, `closePaymentModal()`, `showReceipt(int)`.
- Produces: `finalizeOrder()` now safe to call repeatedly across split tabs; no new public interface for later tasks (Task 5 only reads `$splitGroups[*]['paid']` from the blade, already produced by Task 1).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Livewire/Pos/CashierSplitBillTest.php`:

```php
    public function test_finalize_order_in_split_mode_marks_tab_paid_and_advances_to_next_unpaid(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->set('cashReceived', 20000)
            ->call('finalizeOrder');

        $andi = \App\Models\Order::where('customer_name', 'Andi')->first();
        $budi = \App\Models\Order::where('customer_name', 'Budi')->first();

        $this->assertSame(\App\Enum\Orders\OrderStatus::Completed, $andi->fresh()->status);
        $this->assertSame(\App\Enum\Orders\OrderStatus::Pending, $budi->fresh()->status);

        $component->assertSet('showPaymentModal', true)
            ->assertSet('activeSplitIndex', 1)
            ->assertSet('currentOrderId', $budi->id);

        $this->assertTrue($component->instance()->splitGroups[0]['paid']);
        $this->assertFalse($component->instance()->splitGroups[1]['paid']);
    }

    public function test_finalize_order_on_last_split_tab_closes_modal_and_resets_state(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->set('cashReceived', 20000)
            ->call('finalizeOrder') // pays Andi, advances to Budi
            ->set('cashReceived', 20000)
            ->call('finalizeOrder'); // pays Budi, should close out

        $andi = \App\Models\Order::where('customer_name', 'Andi')->first();
        $budi = \App\Models\Order::where('customer_name', 'Budi')->first();

        $this->assertSame(\App\Enum\Orders\OrderStatus::Completed, $andi->fresh()->status);
        $this->assertSame(\App\Enum\Orders\OrderStatus::Completed, $budi->fresh()->status);

        $component->assertSet('showPaymentModal', false)
            ->assertSet('splitGroups', [])
            ->assertSet('splitMode', false)
            ->assertSet('activeSplitIndex', null)
            ->assertSet('cart', []);
    }

    public function test_finalize_order_cannot_repay_an_already_paid_split_tab(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->set('cashReceived', 20000)
            ->call('finalizeOrder'); // pays Andi, advances to Budi

        $andi = \App\Models\Order::where('customer_name', 'Andi')->first();
        $andiPaidAt = $andi->fresh()->updated_at;

        $component->call('switchSplitTab', 0) // go back to view Andi's now-paid tab
            ->set('cashReceived', 20000)
            ->call('finalizeOrder'); // must no-op

        $this->assertSame(\App\Enum\Orders\OrderStatus::Completed, $andi->fresh()->status);
        $this->assertTrue($andiPaidAt->equalTo($andi->fresh()->updated_at));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CashierSplitBillTest`
Expected: FAIL — current `finalizeOrder()` always calls `resetCashier()`/`closePaymentModal()`/`showReceipt()`, so `showPaymentModal` ends up `false` after the first split payment instead of staying `true` on tab 2.

- [ ] **Step 3: Implement in `app/Livewire/Pos/Cashier.php`**

Replace `finalizeOrder()` (lines 345-357):

```php
    public function finalizeOrder()
    {
        $this->ensureActivePaymentMethod();

        if ($this->paymentMethod === 'cash' && empty($this->cashReceived)) return;
        if ($this->paymentMethod !== 'cash' && ! $this->paymentConfirmed) return;

        if ($this->activeSplitIndex !== null && ($this->splitGroups[$this->activeSplitIndex]['paid'] ?? false)) {
            return;
        }

        $order = $this->orderService->finalizeOrder($this->currentOrderId, $this->paymentMethod, $this->cashReceived, $this->orderType);

        if ($this->activeSplitIndex === null) {
            $this->resetCashier();
            $this->closePaymentModal();
            $this->showReceipt($order->id);
            return;
        }

        $this->splitGroups[$this->activeSplitIndex]['paid'] = true;

        $nextUnpaid = collect($this->splitGroups)->search(fn ($group) => ! $group['paid']);

        if ($nextUnpaid !== false) {
            $this->switchSplitTab($nextUnpaid);
            return;
        }

        $this->resetCashier();
        $this->reset(['splitGroups', 'splitMode', 'activeSplitIndex']);
        $this->closePaymentModal();
        $this->showReceipt($order->id);
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=CashierSplitBillTest`
Expected: PASS (including `test_a_split_order_can_be_paid_from_the_drafts_list` and `test_a_finalized_split_order_renders_its_receipt`, which exercise `finalizeOrder()` outside split mode via `finalizeTableOrder()` and must still pass unchanged).

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/Pos/Cashier.php tests/Feature/Livewire/Pos/CashierSplitBillTest.php
git commit -m "feat: advance through split payment tabs on finalizeOrder"
```

---

### Task 4: QRIS amount follows the active split group

**Files:**
- Modify: `app/Livewire/Pos/Cashier.php:180-183` (`openQrisPreviewModal()`)
- Modify: `tests/Feature/Livewire/Pos/CashierSplitBillTest.php` (append tests)

**Interfaces:**
- Consumes: Task 2's `splitGroupTotal(int)`; `$activeSplitIndex`; existing `$this->total` (cart-wide, from `CartCalculation`).
- Produces: `previewQrisAmount` now correct for both split and non-split flows — no new public interface.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Livewire/Pos/CashierSplitBillTest.php`:

```php
    public function test_open_qris_preview_modal_uses_active_split_group_total(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->call('openQrisPreviewModal');

        $this->assertSame(22200, $component->instance()->previewQrisAmount);
    }

    public function test_open_qris_preview_modal_uses_cart_total_outside_split_mode(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('openQrisPreviewModal');

        $this->assertSame(22200, $component->instance()->previewQrisAmount);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CashierSplitBillTest`
Expected: FAIL — `test_open_qris_preview_modal_uses_active_split_group_total` gets the full-cart amount (40000-based total) instead of Andi's group total (22200), since `openQrisPreviewModal()` currently always uses `$this->total`.

- [ ] **Step 3: Implement in `app/Livewire/Pos/Cashier.php`**

Replace `openQrisPreviewModal()` (lines 180-183):

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

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=CashierSplitBillTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/Pos/Cashier.php tests/Feature/Livewire/Pos/CashierSplitBillTest.php
git commit -m "fix: use active split group total for QRIS preview amount"
```

---

### Task 5: Payment modal UI — tab bar + per-group amounts

**Files:**
- Modify: `resources/views/livewire/pos/payment_modal.blade.php`
- Modify: `resources/css/cashier.css`

No new PHP — wires Tasks 1/2/3/4's state into markup. No automated test (presentational); verify manually per Step 5.

- [ ] **Step 1: Add display variables and the tab bar**

In `resources/views/livewire/pos/payment_modal.blade.php`, replace the opening (lines 1-3):

```blade
<div class="payment-modal-card">
    <div class="modal-left-content">
        <div class="modal-header">
```

with:

```blade
<div class="payment-modal-card">
    @php
        $displaySubtotal = $activeSplitIndex !== null ? $this->splitGroupSubtotal($activeSplitIndex) : $this->subtotal;
        $displayTax = $activeSplitIndex !== null ? $this->splitGroupTax($activeSplitIndex) : $this->taxAmount;
        $displayTotal = $activeSplitIndex !== null ? $this->splitGroupTotal($activeSplitIndex) : $this->total;
        $displayName = $activeSplitIndex !== null ? $splitGroups[$activeSplitIndex]['name'] : $this->customerName;
        $displayChange = $this->cashReceived !== null ? max(0, $this->cashReceived - $displayTotal) : null;
    @endphp
    <div class="modal-left-content">
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
        <div class="modal-header">
```

- [ ] **Step 2: Route the QRIS block through the active group's total**

Replace (lines 50-56):

```blade
        @if($this->paymentMethod !== 'cash')
            @if($this->qrisImage)
                <div class="payment-notice">
                    <i class="bi bi-qr-code"></i>
                    <p>Minta pelanggan scan QR ini untuk membayar Rp {{ number_format($this->total) }}.</p>
                </div>
                <div class="qris-qr-wrap">{!! $this->qrisImage !!}</div>
```

with:

```blade
        @if($this->paymentMethod !== 'cash')
            @if($this->qrisImageForAmount($displayTotal))
                <div class="payment-notice">
                    <i class="bi bi-qr-code"></i>
                    <p>Minta pelanggan scan QR ini untuk membayar Rp {{ number_format($displayTotal) }}.</p>
                </div>
                <div class="qris-qr-wrap">{!! $this->qrisImageForAmount($displayTotal) !!}</div>
```

- [ ] **Step 3: Route the summary panel through the display variables**

Replace the summary block through the submit button's opening tag (lines 121-161):

```blade
            <div class="price-details">
                <div class="price-row text-secondary">
                    <span>Pelanggan</span>
                    <span id="summaryCustomer">{{ $this->customerName }}</span>
                </div>
                <div class="price-row text-secondary">
                    <span>Subtotal</span>
                    <span id="summarySubtotal">Rp {{ number_format($this->subtotal) }}</span>
                </div>
                <div class="price-row text-secondary">
                    <span>Tax (11%)</span>
                    <span id="summaryTax">Rp {{ number_format($this->taxAmount) }}</span>
                </div>
                <div class="price-row total-row">
                    <span>Total Tagihan</span>
                    <span id="summaryTotal" class="text-blue">Rp {{ number_format($this->total) }}</span>
                </div>
            </div>

            @php
                $isInsufficient = $this->cashReceived !== null && $this->cashReceived < $this->total;
                $canFinalize = $this->paymentMethod === 'cash'
                    ? (! $isInsufficient && $cashReceived !== null)
                    : $paymentConfirmed;
            @endphp

            <div class="@if($isInsufficient) change-box-danger @else change-box-success @endif">
                <div class="@if($isInsufficient) change-title-danger @else change-title-success @endif">Kembalian</div>

                @if($isInsufficient)
                    <p class="change-notice-danger">Jumlah uang diterima kurang.</p>
                @endif

                <div id="summaryChange" class="@if($isInsufficient) change-amount-danger @else change-amount-success @endif">
                    Rp {{ number_format($this->change ?? 0) }}
                </div>
            </div>
        </div>

        <div class="submit-action-wrapper">
            <button type="button" wire:click="finalizeOrder" class="btn-submit-payment" @if(! $canFinalize) disabled @endif>
```

with:

```blade
            <div class="price-details">
                <div class="price-row text-secondary">
                    <span>Pelanggan</span>
                    <span id="summaryCustomer">{{ $displayName }}</span>
                </div>
                <div class="price-row text-secondary">
                    <span>Subtotal</span>
                    <span id="summarySubtotal">Rp {{ number_format($displaySubtotal) }}</span>
                </div>
                <div class="price-row text-secondary">
                    <span>Tax (11%)</span>
                    <span id="summaryTax">Rp {{ number_format($displayTax) }}</span>
                </div>
                <div class="price-row total-row">
                    <span>Total Tagihan</span>
                    <span id="summaryTotal" class="text-blue">Rp {{ number_format($displayTotal) }}</span>
                </div>
            </div>

            @php
                $isInsufficient = $this->cashReceived !== null && $this->cashReceived < $displayTotal;
                $isPaidSplitTab = $activeSplitIndex !== null && ($splitGroups[$activeSplitIndex]['paid'] ?? false);
                $canFinalize = ! $isPaidSplitTab && ($this->paymentMethod === 'cash'
                    ? (! $isInsufficient && $cashReceived !== null)
                    : $paymentConfirmed);
            @endphp

            <div class="@if($isInsufficient) change-box-danger @else change-box-success @endif">
                <div class="@if($isInsufficient) change-title-danger @else change-title-success @endif">Kembalian</div>

                @if($isInsufficient)
                    <p class="change-notice-danger">Jumlah uang diterima kurang.</p>
                @endif

                <div id="summaryChange" class="@if($isInsufficient) change-amount-danger @else change-amount-success @endif">
                    Rp {{ number_format($displayChange ?? 0) }}
                </div>
            </div>
        </div>

        <div class="submit-action-wrapper">
            <button type="button" wire:click="finalizeOrder" class="btn-submit-payment" @if(! $canFinalize) disabled @endif>
```

- [ ] **Step 4: Add tab bar styles to `resources/css/cashier.css`**

Append:

```css
.payment-split-tabs {
    display: flex;
    gap: 4px;
    overflow-x: auto;
    padding: 8px 8px 0;
    margin: -1.5rem -1.5rem 1rem;
    border-bottom: 1px solid #f3f4f6;
}

.payment-split-tab {
    flex-shrink: 0;
    padding: 8px 14px;
    border: 1px solid #e2e8f0;
    border-bottom: none;
    border-radius: 8px 8px 0 0;
    background: #f8fafc;
    color: #64748b;
    font-size: 13px;
    cursor: pointer;
}

.payment-split-tab.active {
    background: #ffffff;
    color: #0f172a;
    font-weight: 600;
    border-color: #cbd5e1;
}

.payment-split-tab.is-paid {
    color: #16a34a;
}

.payment-split-tab.is-paid i {
    margin-right: 4px;
}
```

(The negative margin bleeds the tab strip to the edges of `.modal-left-content`, which has `padding: 1.5rem` per `resources/css/payment-modal.css:36`.)

- [ ] **Step 5: Manually verify**

Run: `npm run dev` (or `npm run build`), open `/cashier`, add products with qty > 1, click "Split Bill", name 2+ groups, drag all units in, click "Checkout Split". Confirm:
- The payment modal opens immediately (no redirect to Drafts) with a tab per group, first tab active.
- Paying the first tab (cash, enough `cashReceived`) marks it ✓ and auto-switches to the next unpaid tab; modal stays open.
- Clicking back to the paid tab shows its amounts but the submit button stays disabled.
- Selecting QRIS on a tab shows a QR sized to that group's total, not the whole cart.
- Paying the last tab closes the modal and redirects to that order's receipt page.
- Closing the modal partway through (browser back or the X button) leaves the unpaid group(s) as `Pending` orders visible in the Drafts modal, payable from there as before.

- [ ] **Step 6: Commit**

```bash
git add resources/views/livewire/pos/payment_modal.blade.php resources/css/cashier.css
git commit -m "feat: add payment-modal split tabs UI to Cashier"
```
