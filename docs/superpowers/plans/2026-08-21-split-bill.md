# Split Bill Implementation Plan

# Skills
/superpowers
/ui-ux-pro-max
/ponytail
/caveman

# Commit Rules
- Don't commit after you already create 1 feature, ask me to test on browser. and i will ask you to commit
- Commit with oneline, with no co-author
- always use skills /commit-works

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let cashier or self-order guests split one cart into N named sub-orders (per person) before checkout, each with its own Order, Payment, and receipt.

**Architecture:** Split state (group names + per-product-unit assignments) lives in `App\Traits\CartCalculation`, already composed by both `Cashier` and `LandingPage`. Splitting never mutates `$cart` — it's an assignment map on top of it, so it's cancellable any time. Checkout loops the groups and calls the existing `OrderService::processOrder()` once per group — no new Order/Payment columns, no new backend concept. Cashier-side split orders land in the existing Drafts list (same `status=pending, table_id=null` query already used for drafts) and get a "Bayar" button reusing the existing `finalizeTableOrder()`. Self-order split orders land in the existing per-table order lists (`fromTableOrders`/`processingTableOrders`/etc.) untouched. Drag-and-drop uses native HTML5 DnD + Alpine (both already shipped via Livewire) — no new JS dependency.

**Tech Stack:** Laravel, Livewire 3 (+ bundled Alpine), Blade, PHPUnit (`RefreshDatabase` + `Livewire::test()`), plain CSS per page.

## Global Constraints

- No new database columns/migrations. Split-group name is stored as the existing `orders.customer_name`. Grouping of split orders is implicit (same `table_id`, created in the same request) — no batch/session id, per the approved design's explicit scope cut.
- Split assignment is keyed by **product id**, not cart array index — cart items are already unique-per-product (`addToCart` merges into an existing line), so this sidesteps re-indexing bugs when `removeFromCart` re-indexes `$cart`.
- Minimum 2 groups to use split (a "split" of 1 isn't a split). Checkout is blocked while any cart unit is unassigned (no auto "shared" bucket).
- Tax/total per split order is computed independently by the existing `CartCalculatorService`/`OrderService` path — no proportional redistribution logic (there's no cart-level discount to redistribute; `discount` is hardcoded `0` in `OrderService::processOrder()`).
- Every new PHP method that isn't trivially a one-liner getter gets a PHPUnit test exercising it through the Livewire component (per this repo's existing test style — see `tests/Feature/Livewire/Pos/CashierPaymentMethodTest.php`).
- Follow existing code style: no doc blocks, no comments except where a non-obvious constraint needs explaining (e.g. why assignments are keyed by product id).

---

### Task 1: Split-bill state & core logic in `CartCalculation` trait

**Files:**
- Modify: `app/Traits/CartCalculation.php`
- Test: `tests/Feature/Livewire/Pos/CashierSplitBillTest.php` (new)

**Interfaces:**
- Consumes: existing `$cart` array shape (`['id','name','price','qty','subtotal','image']`), existing `removeFromCart(int $index)`.
- Produces (used by Task 2 and Task 4):
  - `public bool $splitMode`
  - `public array $splitGroups` — `[ ['name' => string, 'assignments' => [productId => qty]] ]`
  - `toggleSplitMode(): void`
  - `addSplitGroup(string $name): void`
  - `removeSplitGroup(int $groupIndex): void`
  - `assignUnitToGroup(int $groupIndex, int $productId): void`
  - `unassignUnitFromGroup(int $groupIndex, int $productId): void`
  - `unassignedQty(int $productId): int`
  - `splitGroupSubtotal(int $groupIndex): float`
  - `canCheckoutSplit(): bool`
  - `buildSplitCartItems(int $groupIndex): array` — same shape as `$cart` entries, ready for `OrderService::processOrder()`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Livewire/Pos/CashierSplitBillTest.php`:

```php
<?php

namespace Tests\Feature\Livewire\Pos;

use App\Livewire\Pos\Cashier;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashierSplitBillTest extends TestCase
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

    public function test_assigning_a_unit_moves_it_out_of_the_unassigned_pool(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id) // qty 1
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id);

        $this->assertSame(0, $component->instance()->unassignedQty($product->id));
        $this->assertSame(20000.0, $component->instance()->splitGroupSubtotal(0));
    }

    public function test_qty_can_be_split_across_groups(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Es Teh', 5000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('incrementQuantity', 0) // qty 3
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('assignUnitToGroup', 1, $product->id);

        $this->assertSame(0, $component->instance()->unassignedQty($product->id));
        $this->assertSame(5000.0, $component->instance()->splitGroupSubtotal(0));
        $this->assertSame(10000.0, $component->instance()->splitGroupSubtotal(1));
    }

    public function test_cannot_assign_more_units_than_the_cart_line_has(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Es Teh', 5000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id) // qty 1
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id); // no unassigned units left

        $this->assertSame(1, $component->instance()->splitGroups[0]['assignments'][$product->id]);
        $this->assertArrayNotHasKey($product->id, $component->instance()->splitGroups[1]['assignments']);
    }

    public function test_checkout_split_is_blocked_until_every_unit_is_assigned(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Es Teh', 5000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id); // only 1 of 2 assigned

        $this->assertFalse($component->instance()->canCheckoutSplit());
    }

    public function test_checkout_split_requires_at_least_two_named_groups(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Es Teh', 5000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('addSplitGroup', 'Andi')
            ->call('assignUnitToGroup', 0, $product->id);

        $this->assertFalse($component->instance()->canCheckoutSplit());
    }

    public function test_removing_a_cart_item_clears_its_assignments(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Es Teh', 5000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('removeFromCart', 0);

        $this->assertArrayNotHasKey($product->id, $component->instance()->splitGroups[0]['assignments']);
    }

    public function test_toggle_split_mode_off_clears_groups(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Es Teh', 5000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('addSplitGroup', 'Andi')
            ->call('toggleSplitMode') // on -> off is a second call; do it twice below
            ->call('toggleSplitMode');

        $this->assertSame([], $component->instance()->splitGroups);
    }

    public function test_unassign_unit_from_group_returns_it_to_the_pool(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Es Teh', 5000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('unassignUnitFromGroup', 0, $product->id);

        $this->assertSame(1, $component->instance()->unassignedQty($product->id));
        $this->assertArrayNotHasKey($product->id, $component->instance()->splitGroups[0]['assignments']);
    }

    public function test_remove_split_group_reindexes_remaining_groups(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Es Teh', 5000);

        $component = Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('addSplitGroup', 'Citra')
            ->call('removeSplitGroup', 0); // drop Andi

        $this->assertCount(2, $component->instance()->splitGroups);
        $this->assertSame('Budi', $component->instance()->splitGroups[0]['name']);
        $this->assertSame('Citra', $component->instance()->splitGroups[1]['name']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CashierSplitBillTest`
Expected: FAIL — `addSplitGroup`/`assignUnitToGroup`/etc. don't exist yet (method not found errors).

- [ ] **Step 3: Implement in `app/Traits/CartCalculation.php`**

Add two public properties right after `public ?int $cashReceived = null;`:

```php
    public bool $splitMode = false;
    public array $splitGroups = [];
```

Replace `removeFromCart` with a version that also strips stale assignments:

```php
    public function removeFromCart($index)
    {
        $productId = $this->cart[$index]['id'] ?? null;

        unset($this->cart[$index]);
        $this->cart = array_values($this->cart); // re-index

        if ($productId !== null) {
            $this->clearSplitAssignmentsForProduct($productId);
        }
    }
```

Add the following methods at the end of the trait, before the closing `}` (after `resolveProduct`):

```php
    public function toggleSplitMode(): void
    {
        $this->splitMode = ! $this->splitMode;

        if (! $this->splitMode) {
            $this->splitGroups = [];
        }
    }

    public function addSplitGroup(string $name): void
    {
        $name = trim($name);

        if ($name === '') {
            return;
        }

        $this->splitGroups[] = ['name' => $name, 'assignments' => []];
    }

    public function removeSplitGroup(int $groupIndex): void
    {
        unset($this->splitGroups[$groupIndex]);
        $this->splitGroups = array_values($this->splitGroups);
    }

    public function assignUnitToGroup(int $groupIndex, int $productId): void
    {
        if (! isset($this->splitGroups[$groupIndex]) || $this->unassignedQty($productId) <= 0) {
            return;
        }

        $current = $this->splitGroups[$groupIndex]['assignments'][$productId] ?? 0;
        $this->splitGroups[$groupIndex]['assignments'][$productId] = $current + 1;
    }

    public function unassignUnitFromGroup(int $groupIndex, int $productId): void
    {
        if (! isset($this->splitGroups[$groupIndex]['assignments'][$productId])) {
            return;
        }

        $remaining = $this->splitGroups[$groupIndex]['assignments'][$productId] - 1;

        if ($remaining <= 0) {
            unset($this->splitGroups[$groupIndex]['assignments'][$productId]);
        } else {
            $this->splitGroups[$groupIndex]['assignments'][$productId] = $remaining;
        }
    }

    public function unassignedQty(int $productId): int
    {
        $cartItem = collect($this->cart)->firstWhere('id', $productId);

        if (! $cartItem) {
            return 0;
        }

        $assigned = 0;

        foreach ($this->splitGroups as $group) {
            $assigned += $group['assignments'][$productId] ?? 0;
        }

        return max(0, $cartItem['qty'] - $assigned);
    }

    public function splitGroupSubtotal(int $groupIndex): float
    {
        if (! isset($this->splitGroups[$groupIndex])) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($this->splitGroups[$groupIndex]['assignments'] as $productId => $qty) {
            $cartItem = collect($this->cart)->firstWhere('id', $productId);

            if ($cartItem) {
                $total += $cartItem['price'] * $qty;
            }
        }

        return $total;
    }

    public function canCheckoutSplit(): bool
    {
        if (empty($this->cart) || count($this->splitGroups) < 2) {
            return false;
        }

        foreach ($this->splitGroups as $group) {
            if (trim($group['name']) === '' || empty($group['assignments'])) {
                return false;
            }
        }

        foreach ($this->cart as $item) {
            if ($this->unassignedQty($item['id']) > 0) {
                return false;
            }
        }

        return true;
    }

    public function buildSplitCartItems(int $groupIndex): array
    {
        if (! isset($this->splitGroups[$groupIndex])) {
            return [];
        }

        $items = [];

        foreach ($this->splitGroups[$groupIndex]['assignments'] as $productId => $qty) {
            $cartItem = collect($this->cart)->firstWhere('id', $productId);

            if (! $cartItem || $qty <= 0) {
                continue;
            }

            $items[] = [
                'id' => $cartItem['id'],
                'name' => $cartItem['name'],
                'price' => $cartItem['price'],
                'qty' => $qty,
                'subtotal' => $cartItem['price'] * $qty,
            ];
        }

        return $items;
    }

    private function clearSplitAssignmentsForProduct(int $productId): void
    {
        foreach ($this->splitGroups as $index => $group) {
            unset($this->splitGroups[$index]['assignments'][$productId]);
        }
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=CashierSplitBillTest`
Expected: PASS (all 9 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Traits/CartCalculation.php tests/Feature/Livewire/Pos/CashierSplitBillTest.php
git commit -m "feat: add split-bill cart assignment logic to CartCalculation"
```

---

### Task 2: Cashier checkout + payment flow for split orders

**Files:**
- Modify: `app/Livewire/Pos/Cashier.php`
- Modify: `tests/Feature/Livewire/Pos/CashierSplitBillTest.php`

**Interfaces:**
- Consumes: Task 1's `canCheckoutSplit()`, `buildSplitCartItems(int)`, `$splitGroups`, `$splitMode`; existing `OrderService::processOrder()` (unchanged signature); existing `finalizeTableOrder(int $id)` (unchanged, reused as-is for paying a split order).
- Produces: `checkoutSplit(): \Illuminate\Http\RedirectResponse` — creates one `Order`+`Payment` per split group and redirects back to the cashier page with a flash message, same pattern as `saveDraft()`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Livewire/Pos/CashierSplitBillTest.php`:

```php
    public function test_checkout_split_creates_one_order_per_group(): void
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
            ->assertSet('cart', [])
            ->assertSet('splitMode', false)
            ->assertSet('splitGroups', []);

        $andi = \App\Models\Order::where('customer_name', 'Andi')->first();
        $budi = \App\Models\Order::where('customer_name', 'Budi')->first();

        $this->assertNotNull($andi);
        $this->assertNotNull($budi);
        $this->assertSame(1, $andi->items()->count());
        $this->assertSame(20000.0, (float) $andi->items()->first()->subtotal);
        $this->assertSame(\App\Enum\Orders\OrderStatus::Pending, $andi->status);
        $this->assertNull($andi->table_id);
        $this->assertNotNull($andi->payment);
        $this->assertSame(\App\Enum\Orders\PaymentStatus::Pending, $andi->payment->status);
    }

    public function test_checkout_split_does_nothing_when_not_ready(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('addSplitGroup', 'Andi') // only 1 group, still unassigned
            ->call('checkoutSplit');

        $this->assertSame(0, \App\Models\Order::count());
    }

    public function test_a_split_order_can_be_paid_from_the_drafts_list(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->createProduct('Nasi Goreng', 20000);

        Livewire::test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('checkoutSplit');

        $andi = \App\Models\Order::where('customer_name', 'Andi')->first();

        Livewire::test(Cashier::class)
            ->call('finalizeTableOrder', $andi->id)
            ->assertSet('showPaymentModal', true)
            ->set('cashReceived', 20000)
            ->call('finalizeOrder');

        $this->assertSame(\App\Enum\Orders\OrderStatus::Completed, $andi->fresh()->status);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=CashierSplitBillTest`
Expected: FAIL — `checkoutSplit` method not found.

- [ ] **Step 3: Implement in `app/Livewire/Pos/Cashier.php`**

Add after `checkout()`:

```php
    public function checkoutSplit()
    {
        if (! $this->canCheckoutSplit()) return;

        foreach ($this->splitGroups as $index => $group) {
            $this->orderService->processOrder(
                $this->buildSplitCartItems($index),
                null,
                $group['name'],
                $this->orderType,
                null
            );
        }

        $this->reset(['cart', 'splitGroups', 'splitMode', 'customerName']);

        return redirect()->route('filament.admin.pages.cashier')
            ->with('message', 'Split bill berhasil dibuat. Bayar tiap pesanan dari menu Draft.')
            ->with('type', 'success');
    }
```

Update `voidCart()` so cancelling the cart also drops any in-progress split:

```php
    public function voidCart(): void
    {
        $this->reset('cart', 'splitGroups', 'splitMode');
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=CashierSplitBillTest`
Expected: PASS (all 12 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/Pos/Cashier.php tests/Feature/Livewire/Pos/CashierSplitBillTest.php
git commit -m "feat: checkout a split cart into per-person orders in Cashier"
```

---

### Task 3: Cashier UI — split panel, drag-and-drop, Drafts "Bayar" button

**Files:**
- Create: `resources/views/livewire/pos/split_bill_panel.blade.php`
- Modify: `resources/views/livewire/pos/cashier.blade.php`
- Modify: `resources/views/livewire/pos/drafts_modal.blade.php`
- Modify: `resources/css/cashier.css`

No new PHP — this wires the Task 1/2 methods into markup. No automated test (presentational); verify manually per Step 4.

- [ ] **Step 1: Create the split panel partial**

`resources/views/livewire/pos/split_bill_panel.blade.php`:

```blade
<div class="split-bill-panel">
    <div class="split-bill-pool">
        <h4>Belum Dibagi</h4>
        @forelse($cart as $item)
            @php $left = $this->unassignedQty($item['id']); @endphp
            @if($left > 0)
                <div class="split-pool-row">
                    <span class="split-pool-name">{{ $item['name'] }}</span>
                    <div class="split-pool-chips">
                        @for($i = 0; $i < $left; $i++)
                            <span class="split-unit-chip"
                                draggable="true"
                                x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $item['id'] }}')">
                                Rp {{ number_format($item['price'], 0, ',', '.') }}
                            </span>
                        @endfor
                    </div>
                </div>
            @endif
        @empty
            <p class="split-pool-empty">Semua item sudah dibagi.</p>
        @endforelse
    </div>

    <div class="split-bill-groups">
        @foreach($splitGroups as $groupIndex => $group)
            <div class="split-group"
                x-on:dragover.prevent
                x-on:drop.prevent="$wire.assignUnitToGroup({{ $groupIndex }}, parseInt($event.dataTransfer.getData('text/plain')))">
                <div class="split-group-header">
                    <span>{{ $group['name'] }}</span>
                    <button type="button" wire:click="removeSplitGroup({{ $groupIndex }})" title="Hapus grup">&times;</button>
                </div>

                @forelse($group['assignments'] as $productId => $qty)
                    @php $product = collect($cart)->firstWhere('id', $productId); @endphp
                    @if($product)
                        <div class="split-group-item" wire:click="unassignUnitFromGroup({{ $groupIndex }}, {{ $productId }})" title="Klik untuk batalkan">
                            {{ $qty }}x {{ $product['name'] }}
                        </div>
                    @endif
                @empty
                    <p class="split-group-empty">Drag item ke sini</p>
                @endforelse

                <div class="split-group-subtotal">Rp {{ number_format($this->splitGroupSubtotal($groupIndex), 0, ',', '.') }}</div>
            </div>
        @endforeach

        <div class="split-add-group" x-data="{ name: '' }">
            <input type="text" x-model="name" placeholder="Nama orang" @keydown.enter="$wire.addSplitGroup(name); name = ''">
            <button type="button" @click="$wire.addSplitGroup(name); name = ''">+ Tambah Orang</button>
        </div>
    </div>

    <button type="button" class="checkout-btn split-checkout-btn" wire:click="checkoutSplit" @if(! $this->canCheckoutSplit()) disabled @endif>
        <i class="bi bi-credit-card-fill"></i>
        <span>Checkout Split ({{ count($splitGroups) }} pesanan)</span>
    </button>
</div>
```

- [ ] **Step 2: Wire the panel into `cashier.blade.php`**

Replace the `cart-items` block (lines 109–138 — the `@if(count($cart) === 0) ... @else @foreach(...) @endforeach @endif`) with:

```blade
            <div class="cart-items">
                @if(count($cart) === 0)
                    <div class="empty-cart">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                        <p>Belum ada pesanan</p>
                    </div>
                @elseif($splitMode)
                    @include('livewire.pos.split_bill_panel')
                @else
                    @foreach($cart as $index => $item)
                        <div class="cart-item">
                            <div class="cart-item-top">
                                <span class="item-name">{{ $item['name'] }}</span>
                                <div class="cart-item-actions">
                                    <div class="qty-controls">
                                        <button class="qty-btn" wire:click="decrementQuantity({{ $index }})">-</button>
                                        <span class="qty-display">{{ $item['qty'] }}</span>
                                        <button class="qty-btn" wire:click="incrementQuantity({{ $index }})">+</button>
                                    </div>
                                    <button class="remove-btn" wire:click="removeFromCart({{ $index }})" title="Hapus">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="cart-item-bottom">
                                <span class="item-price">Rp {{ number_format($item['price'], 0, ',', '.') }}</span>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
```

In the `cart-summary` block, wrap the "Nama Pelanggan" row with `@unless($splitMode) ... @endunless`, and wrap the existing `buttons-row` `draft-btn` + `checkout-btn` with the same guard, adding a "Split Bill" toggle that's always visible next to "Kosongkan":

```blade
                @unless($splitMode)
                <div class="summary-row">
                    <span>Nama Pelanggan</span>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="customerName"
                        placeholder="Masukkan nama pelanggan"
                        x-data
                        @input="$el.value = $el.value.replace(/[^a-zA-Z\s]/g, '')">
                </div>
                @endunless
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>Rp {{ number_format($this->subtotal, 0, ',', '.') }}</span>
                </div>
                <div class="summary-row">
                    <span>Pajak (11%)</span>
                    <span>Rp {{ number_format($this->taxAmount, 0, ',', '.') }}</span>
                </div>
                <div class="summary-row total-row">
                    <span>Total</span>
                    <span>Rp {{ number_format($this->total, 0, ',', '.') }}</span>
                </div>

                <div class="buttons-row">
                    <div class="buttons-row-secondary">
                        <button class="secondary-btn void-btn" wire:click="voidCart" @if(count($cart) === 0) disabled @endif>
                            <i class="bi bi-trash3"></i>
                            <span>Kosongkan</span>
                        </button>
                        <button class="secondary-btn split-toggle-btn" wire:click="toggleSplitMode" @if(count($cart) === 0) disabled @endif>
                            <i class="bi bi-people-fill"></i>
                            <span>{{ $splitMode ? 'Batal Split' : 'Split Bill' }}</span>
                        </button>
                        @unless($splitMode)
                        <button class="secondary-btn draft-btn" wire:click="saveDraft" @if(count($cart) === 0 || empty($customerName)) disabled @endif>
                            <i class="bi bi-save2"></i>
                            <span>Draft</span>
                        </button>
                        @endunless
                    </div>
                    @unless($splitMode)
                    <button class="checkout-btn" wire:click="checkout" @if(count($cart) === 0 || empty($customerName)) disabled @endif>
                        <i class="bi bi-credit-card-fill"></i>
                        <span>Proses Pembayaran</span>
                    </button>
                    @endunless
                </div>
```

- [ ] **Step 3: Add "Bayar" action to `drafts_modal.blade.php`**

In the `draft-actions` div (next to the existing delete button), add:

```blade
                        <div class="draft-actions">
                            <button wire:click.stop="closeDraftsModal(); finalizeTableOrder({{ $draft->id }})" title="Bayar">
                                <i class="bi bi-credit-card"></i>
                            </button>
                            <button wire:click.stop="deleteDraft({{ $draft->id }})" title="Hapus Draft">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
```

- [ ] **Step 4: Add styles to `resources/css/cashier.css`**

Append:

```css
.split-bill-panel {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.split-bill-pool {
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    padding: 10px;
}

.split-pool-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 8px;
}

.split-pool-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.split-unit-chip {
    cursor: grab;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 4px 8px;
    font-size: 12px;
}

.split-bill-groups {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.split-group {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px;
    min-height: 48px;
}

.split-group-header {
    display: flex;
    justify-content: space-between;
    font-weight: 600;
    margin-bottom: 6px;
}

.split-group-item {
    cursor: pointer;
    font-size: 13px;
    padding: 4px 0;
}

.split-group-empty,
.split-pool-empty {
    color: #94a3b8;
    font-size: 12px;
}

.split-group-subtotal {
    text-align: right;
    font-weight: 600;
    margin-top: 6px;
}

.split-add-group {
    display: flex;
    gap: 6px;
}

.split-add-group input {
    flex: 1;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 6px 8px;
}
```

- [ ] **Step 5: Manually verify**

Run: `npm run dev` (or `npm run build`), open `/cashier`, add a couple of products with qty > 1, click "Split Bill", add 2+ named groups, drag chips into groups, confirm "Checkout Split" stays disabled until every chip is assigned, then confirm it creates orders and they show up (with a "Bayar" button) in the Draft modal.

- [ ] **Step 6: Commit**

```bash
git add resources/views/livewire/pos/split_bill_panel.blade.php resources/views/livewire/pos/cashier.blade.php resources/views/livewire/pos/drafts_modal.blade.php resources/css/cashier.css
git commit -m "feat: add split-bill drag-and-drop UI to Cashier"
```

---

### Task 4: Self-order (guest) split checkout

**Files:**
- Modify: `app/Livewire/LandingPage/LandingPage.php`
- Test: `tests/Feature/Livewire/LandingPage/LandingPageSplitBillTest.php` (new)

**Interfaces:**
- Consumes: Task 1's trait methods (already available via `use CartCalculation`); existing `GuestSession`, `OrderService::processOrder()`.
- Produces: `public array $splitOrderNumbers`, `checkoutSplit(): void`, modified `openPaymentModal()` and `closePaymentModal()`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Livewire/LandingPage/LandingPageSplitBillTest.php`. First check how existing self-order tests build a `GuestSession`/`QrCode`/`Table` fixture — reuse that setup (e.g. mirror `tests/Feature/OrderBroadcastTest.php` or the guest-session factories). Read that file to reuse the exact same setup:

```php
<?php

namespace Tests\Feature\Livewire\LandingPage;

use App\Livewire\LandingPage\LandingPage;
use App\Models\Category;
use App\Models\GuestSession;
use App\Models\Order;
use App\Models\Product;
use App\Models\QrCode;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LandingPageSplitBillTest extends TestCase
{
    use RefreshDatabase;

    private function createGuestSession(): GuestSession
    {
        $table = Table::create(['number' => '1', 'barcode' => 'T1']);
        $qrCode = QrCode::create(['table_id' => $table->id]);

        return GuestSession::create([
            'qr_code_id' => $qrCode->id,
            'token' => 'test-session-token',
            'expires_at' => now()->addHour(),
        ]);
    }

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

    public function test_checkout_split_creates_one_order_per_group_on_the_guests_table(): void
    {
        $guestSession = $this->createGuestSession();
        $product = $this->createProduct('Nasi Goreng', 20000);

        Livewire::test(LandingPage::class, ['session_token' => $guestSession->token])
            ->call('addToCart', $product->id)
            ->call('incrementQuantity', 0) // qty 2
            ->call('addSplitGroup', 'Andi')
            ->call('addSplitGroup', 'Budi')
            ->call('assignUnitToGroup', 0, $product->id)
            ->call('assignUnitToGroup', 1, $product->id)
            ->call('checkoutSplit')
            ->assertSet('orderSubmitted', true)
            ->assertSet('cart', []);

        $andi = Order::where('customer_name', 'Andi')->first();
        $budi = Order::where('customer_name', 'Budi')->first();

        $this->assertNotNull($andi);
        $this->assertNotNull($budi);
        $this->assertSame($guestSession->qrCode->table_id, $andi->table_id);
        $this->assertSame($guestSession->qrCode->table_id, $budi->table_id);
    }

    public function test_checkout_split_does_nothing_when_not_ready(): void
    {
        $guestSession = $this->createGuestSession();
        $product = $this->createProduct('Nasi Goreng', 20000);

        Livewire::test(LandingPage::class, ['session_token' => $guestSession->token])
            ->call('addToCart', $product->id)
            ->call('addSplitGroup', 'Andi') // only 1 group
            ->call('checkoutSplit');

        $this->assertSame(0, Order::count());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=LandingPageSplitBillTest`
Expected: FAIL — `checkoutSplit` not found on `LandingPage`. If the `GuestSession`/`QrCode` factory setup above doesn't match this codebase's actual schema, first read `app/Models/GuestSession.php` and `app/Models/QrCode.php` and adjust the fixture to match real column names before proceeding — do not guess past a failing setup.

- [ ] **Step 3: Implement in `app/Livewire/LandingPage/LandingPage.php`**

Add a new public property near `public ?int $lastOrderTotal = null;`:

```php
    public array $splitOrderNumbers = [];
```

Replace `openPaymentModal()`:

```php
    public function openPaymentModal()
    {
        if ($this->splitMode) {
            if (! $this->canCheckoutSplit()) return;
        } elseif (empty($this->cart)) {
            return;
        }

        $this->showPaymentModal = true;
    }
```

Replace `closePaymentModal()`:

```php
    public function closePaymentModal()
    {
        $this->showPaymentModal = false;

        if ($this->orderSubmitted) {
            $this->reset(['customerName', 'paymentMethod', 'orderSubmitted', 'lastOrderNumber', 'lastOrderTotal', 'currentOrderId', 'splitOrderNumbers']);
            $this->ensureActivePaymentMethod();
        }
    }
```

Add after `checkout()`:

```php
    public function checkoutSplit(): void
    {
        if (! $this->canCheckoutSplit()) return;

        $guestSession = GuestSession::findOrFail($this->guestSessionId);

        if ($guestSession->isExpired()) {
            abort(410, 'Session expired. Please scan the QR code again.');
        }

        $this->ensureActivePaymentMethod();

        $orderNumbers = [];

        foreach ($this->splitGroups as $index => $group) {
            $order = $this->orderService->processOrder(
                $this->buildSplitCartItems($index),
                $guestSession->qrCode->table_id,
                $group['name'],
                $this->orderType,
                null,
                $this->paymentMethod
            );

            $orderNumbers[] = $order->order_number;
            $this->dispatch('order-placed', orderId: $order->id);
        }

        $this->splitOrderNumbers = $orderNumbers;
        $this->orderSubmitted = true;
        $this->reset(['cart', 'splitGroups', 'splitMode']);
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=LandingPageSplitBillTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/LandingPage/LandingPage.php tests/Feature/Livewire/LandingPage/LandingPageSplitBillTest.php
git commit -m "feat: let self-order guests submit a split cart as separate orders"
```

---

### Task 5: Self-order UI — split panel + payment modal branching

**Files:**
- Create: `resources/views/livewire/landing-page/split_bill_panel.blade.php`
- Modify: `resources/views/livewire/landing-page/landing-page.blade.php`
- Modify: `resources/views/livewire/landing-page/payment_modal.blade.php`
- Modify: `resources/css/landing-page.css`

No new PHP — wires Task 1/4 methods into markup. No automated test; verify manually per Step 4.

- [ ] **Step 1: Create the split panel partial**

`resources/views/livewire/landing-page/split_bill_panel.blade.php` — same structure as the Cashier one from Task 3 (reuses identical method names since both come from the shared trait), styled with the `split-*` classes defined in Step 3 below:

```blade
<div class="split-bill-panel">
    <div class="split-bill-pool">
        <h4>Belum Dibagi</h4>
        @forelse($cart as $item)
            @php $left = $this->unassignedQty($item['id']); @endphp
            @if($left > 0)
                <div class="split-pool-row">
                    <span class="split-pool-name">{{ $item['name'] }}</span>
                    <div class="split-pool-chips">
                        @for($i = 0; $i < $left; $i++)
                            <span class="split-unit-chip"
                                draggable="true"
                                x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $item['id'] }}')">
                                Rp {{ number_format($item['price'], 0, ',', '.') }}
                            </span>
                        @endfor
                    </div>
                </div>
            @endif
        @empty
            <p class="split-pool-empty">Semua item sudah dibagi.</p>
        @endforelse
    </div>

    <div class="split-bill-groups">
        @foreach($splitGroups as $groupIndex => $group)
            <div class="split-group"
                x-on:dragover.prevent
                x-on:drop.prevent="$wire.assignUnitToGroup({{ $groupIndex }}, parseInt($event.dataTransfer.getData('text/plain')))">
                <div class="split-group-header">
                    <span>{{ $group['name'] }}</span>
                    <button type="button" wire:click="removeSplitGroup({{ $groupIndex }})" title="Hapus grup">&times;</button>
                </div>

                @forelse($group['assignments'] as $productId => $qty)
                    @php $product = collect($cart)->firstWhere('id', $productId); @endphp
                    @if($product)
                        <div class="split-group-item" wire:click="unassignUnitFromGroup({{ $groupIndex }}, {{ $productId }})" title="Klik untuk batalkan">
                            {{ $qty }}x {{ $product['name'] }}
                        </div>
                    @endif
                @empty
                    <p class="split-group-empty">Drag item ke sini</p>
                @endforelse

                <div class="split-group-subtotal">Rp {{ number_format($this->splitGroupSubtotal($groupIndex), 0, ',', '.') }}</div>
            </div>
        @endforeach

        <div class="split-add-group" x-data="{ name: '' }">
            <input type="text" x-model="name" placeholder="Nama orang" @keydown.enter="$wire.addSplitGroup(name); name = ''">
            <button type="button" @click="$wire.addSplitGroup(name); name = ''">+ Tambah Orang</button>
        </div>
    </div>
</div>
```

- [ ] **Step 2: Wire the panel into `landing-page.blade.php`**

Replace the `cart-items` block (lines 342–389) with a version that swaps in the split panel and hides qty controls while splitting:

```blade
                    <div class="cart-items">
                        @if($splitMode)
                            @include('livewire.landing-page.split_bill_panel')
                        @else
                            @foreach ($cart as $key => $item)
                                <div class="cart-item">
                                    @if ($item['image'])
                                        <img src="{{ Storage::url($item['image']) }}" alt="{{ $item['name'] }}"
                                            class="cart-item-img">
                                    @else
                                        <div class="cart-item-img"
                                            style="display:flex; align-items:center; justify-content:center; color:#ccc;">
                                            <svg xmlns="http://www.w3.org/2000/svg" style="width:32px; height:32px;"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    @endif

                                    <div class="cart-item-body">
                                        <h3 class="cart-item-name">{{ $item['name'] }}</h3>
                                        <span class="cart-item-price">Rp
                                            {{ number_format($item['price'] * $item['qty'], 0, ',', '.') }}</span>
                                    </div>
                                    <div class="cart-item-actions">
                                        <div class="quantity-control">
                                            <button wire:click="decrementQuantity({{ $key }})"
                                                class="qty-btn">-</button>
                                            <span class="qty-value">{{ $item['qty'] }}</span>
                                            <button wire:click="incrementQuantity({{ $key }})"
                                                class="qty-btn">+</button>
                                        </div>
                                        <button wire:click="removeFromCart({{ $key }})" class="remove-btn"
                                            aria-label="Hapus">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M3 6h18"></path>
                                                <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                                                <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                                                <line x1="10" y1="11" x2="10" y2="17">
                                                </line>
                                                <line x1="14" y1="11" x2="14" y2="17">
                                                </line>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
```

In the `cart-footer` block, add a split toggle button before `checkout-btn`, and switch the checkout action/label when splitting:

```blade
            @if (count($cart) > 0)
                <div class="cart-footer">
                    <div class="cart-total-row">
                        <div class="cart-summary-row">
                            <span>Subtotal</span>
                            <span>Rp {{ number_format($this->subtotal, 0, ',', '.') }}</span>
                        </div>
                        <div class="cart-summary-row">
                            <span>PPN (11%)</span>
                            <span>Rp {{ number_format($this->taxAmount, 0, ',', '.') }}</span>
                        </div>
                        <div class="total-price">
                            <span>Total</span>
                            <span>Rp {{ number_format($this->total, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    <button type="button" wire:click="toggleSplitMode" class="split-toggle-btn">
                        {{ $splitMode ? 'Batal Split' : 'Split Bill' }}
                    </button>
                    <a wire:click="openPaymentModal" @click="cartOpen = false" class="checkout-btn"
                        @if($splitMode && ! $this->canCheckoutSplit()) style="pointer-events:none;opacity:.5;" @endif>
                        {{ $splitMode ? 'Pesan Semua (' . count($splitGroups) . ')' : 'Pesan Sekarang' }}
                    </a>
                    @if ($table)
                        <p class="cart-table-info">Pesanan akan diantar ke <strong>{{ $table->name }}</strong></p>
                    @endif
                </div>
            @endif
```

- [ ] **Step 3: Branch `payment_modal.blade.php` on `$splitMode`**

Replace the `@if (!$orderSubmitted)` form body's name field + submit button, and the success block's order-number line, per the diffs below (keep everything else in the file as-is).

Replace the "Nama Anda" form-field (lines 15–18) with:

```blade
                @unless($splitMode)
                <div class="form-field">
                    <label for="customerName" class="form-label">Nama Anda</label>
                    <input type="text" id="customerName" wire:model.live.debounce.300ms="customerName" placeholder="Masukkan nama" class="form-input" autocomplete="off">
                </div>
                @else
                <div class="form-field">
                    <span class="form-label">Pesanan akan dipecah jadi:</span>
                    @foreach($splitGroups as $group)
                        <div class="order-summary-row">
                            <span>{{ $group['name'] }}</span>
                            <span>Rp {{ number_format($this->splitGroupSubtotal($loop->index), 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
                @endunless
```

Replace the footer submit button (lines 76–80):

```blade
            <div class="payment-modal-footer">
                @unless($splitMode)
                <button type="button" wire:click="checkout" class="payment-submit-btn" @if (empty($customerName)) disabled @endif>
                    Konfirmasi &amp; Pesan
                </button>
                @else
                <button type="button" wire:click="checkoutSplit" class="payment-submit-btn" @if (! $this->canCheckoutSplit()) disabled @endif>
                    Konfirmasi &amp; Pesan Semua
                </button>
                @endunless
            </div>
```

Replace the success order-number line (line 92). `checkoutSplit()` resets `$splitMode` back to `false` before the success screen renders (Task 4, Step 3), so this block must branch on `$splitOrderNumbers` being non-empty instead of `$splitMode`:

```blade
                @if(empty($splitOrderNumbers))
                <p class="payment-success-order">{{ $lastOrderNumber }}</p>
                @else
                <div class="payment-success-split-list">
                    @foreach($splitOrderNumbers as $orderNumber)
                        <p class="payment-success-order">{{ $orderNumber }}</p>
                    @endforeach
                </div>
                @endif
```

The pre-submit "Nama Anda" swap and footer submit-button swap above are unaffected by this — `$splitMode` is still `true` there since the form is rendered before `checkoutSplit()` runs.

- [ ] **Step 4: Add styles to `resources/css/landing-page.css`**

Append the same ruleset as Task 3 Step 4 (identical class names, same visual language, separate file per this codebase's existing per-page CSS convention):

```css
.split-bill-panel {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.split-bill-pool {
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    padding: 10px;
}

.split-pool-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 8px;
}

.split-pool-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.split-unit-chip {
    cursor: grab;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 4px 8px;
    font-size: 12px;
}

.split-bill-groups {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.split-group {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px;
    min-height: 48px;
}

.split-group-header {
    display: flex;
    justify-content: space-between;
    font-weight: 600;
    margin-bottom: 6px;
}

.split-group-item {
    cursor: pointer;
    font-size: 13px;
    padding: 4px 0;
}

.split-group-empty,
.split-pool-empty {
    color: #94a3b8;
    font-size: 12px;
}

.split-group-subtotal {
    text-align: right;
    font-weight: 600;
    margin-top: 6px;
}

.split-add-group {
    display: flex;
    gap: 6px;
}

.split-add-group input {
    flex: 1;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 6px 8px;
}

.split-toggle-btn {
    width: 100%;
    text-align: center;
    padding: 8px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #f8fafc;
    margin-bottom: 8px;
}
```

- [ ] **Step 5: Manually verify**

Open `/menu/{session_token}` for a seeded table+guest session, add products with qty > 1, toggle "Split Bill", name 2+ groups, drag all units in, confirm "Pesan Semua" is disabled until fully assigned, submit, and confirm N orders arrive in the Cashier's "From Table" list.

- [ ] **Step 6: Commit**

```bash
git add resources/views/livewire/landing-page/split_bill_panel.blade.php resources/views/livewire/landing-page/landing-page.blade.php resources/views/livewire/landing-page/payment_modal.blade.php resources/css/landing-page.css
git commit -m "feat: add split-bill UI to self-order menu"
```
