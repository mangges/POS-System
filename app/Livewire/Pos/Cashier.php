<?php

namespace App\Livewire\Pos;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

use App\Models\Category;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Order;
use App\Models\CashMovement;
use App\Enum\Orders\OrderStatus;
use App\Traits\CartCalculation;
use App\Services\Order\OrderService;
use App\Traits\PaymentMethodSelection;
use App\Enum\Shifts\ShiftStatus;
use App\Models\Shift;
use App\Services\Shift\ShiftService;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

#[Layout('components.layouts.app')]
class Cashier extends Component
{
    public $categories = [];
    public $products = [];
    public ?int $selectedCategory = null;
    public $search = '';
    protected OrderService $orderService;
    public $customerName = '';
    public string $paymentMethod = 'cash';
    public bool $paymentConfirmed = false;
    public array $confirmedPayments = [];
    public ?int $currentOrderId = null;
    public string $orderType = 'dine-in';
    public ?Shift $activeShift = null;
    public string $shiftOpeningCash = '';

    public $activeDraft = null;
    #[Locked]
    public ?int $activeSplitIndex = null;

    public $showPaymentModal = false;
    public $showDraftsModal = false;
    public $showFromTableModal = false;
    public $showKitchenOrdersModal = false;
    public $showQrisPreviewModal = false;
    public ?int $previewQrisAmount = null;
    public $showCashMovementModal = false;
    public string $cashMovementType = 'in';
    public string $cashMovementAmount = '';
    public string $cashMovementReason = '';
    public $showEndShiftModal = false;
    public ?float $endShiftExpectedCash = null;
    public string $endShiftActualCash = '';
    public string $endShiftNote = '';

    use CartCalculation {
        addToCart as protected traitAddToCart;
    }
    use PaymentMethodSelection;

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
        $this->activeShift = Shift::with('user')->where('user_id', Auth::id())->where('status', ShiftStatus::Open)->first();
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

    public function updatedPaymentMethod(): void
    {
        $this->paymentConfirmed = false;
    }

    public function addToCart(...$params)
    {
        if ($this->showDraftsModal) {
            $this->closeDraftsModal();
        }

        $this->traitAddToCart(...$params);
    }

    #[Computed]
    public function draftOrders()
    {
        return Order::with(['table', 'items.product'])->where('status', 'pending')->where('table_id', NULL)->get();
    }

    public function loadDraft(int $id): void
    {
        $this->reset(['cart', 'customerName', 'paymentMethod', 'paymentConfirmed', 'cashReceived', 'currentOrderId', 'orderType', 'activeDraft']);
        $this->ensureActivePaymentMethod();

        $order = Order::with('items')->findOrFail($id);

        $this->activeDraft = $order->id;

        if ($order) {

            $this->cart = $order->items->map(function ($item) {
                $name = Product::findOrFail($item->product_id)->name;
                
                return [
                    'id' => $item->product_id,
                    'name' => $name,
                    'price' => $item->price,
                    'qty' => $item->quantity,
                ];
            })->toArray();
            
            $this->customerName = $order->customer_name;
            $this->orderType = $order->order_type;
            $this->currentOrderId = $order->id;
            $this->closeDraftsModal();
        }
    }

    public function openDraftsModal()
    {
        $this->showDraftsModal = true;
    }

    public function closeDraftsModal()
    {
        $this->showDraftsModal = false;
    }

    public function voidCart(): void
    {
        $this->reset('cart', 'splitGroups', 'splitMode');
    }

    public function saveDraft()
    {
        if (empty($this->cart)) return;

        $this->orderService->processOrder($this->cart, null, $this->customerName, $this->orderType, $this->activeDraft, shiftId: $this->activeShift?->id);

        $this->activeDraft = null;
        $this->reset('cart', 'customerName');
        return redirect()->route('filament.admin.pages.cashier')->with('message', 'Data berhasil disimpan!')->with('type', 'success');
    }

    public function deleteDraft(int $id)
    {
        DB::transaction(function () use ($id) {
            $order = Order::findOrFail($id);

            if ($order->status !== OrderStatus::Pending) {
                return;
            }

            $payment = $order->payment;

            $order->payment_id = null;
            $order->save();

            $order->items()->delete();

            if ($payment) {
                $payment->delete();
            }

            $order->delete();
        });


        return redirect()->back()->with('message', 'Draft berhasil dihapus!')->with('type', 'success');
    }

    public function openFromTableModal()
    {
        $this->showFromTableModal = true;
    }

    public function closeFromTableModal()
    {
        $this->showFromTableModal = false;
    }

    public function openKitchenOrdersModal()
    {
        $this->showKitchenOrdersModal = true;
    }

    public function closeKitchenOrdersModal()
    {
        $this->showKitchenOrdersModal = false;
    }

    public function openQrisPreviewModal()
    {
        $this->previewQrisAmount = (int) round(
            $this->activeSplitIndex !== null
                ? $this->splitGroupTotal($this->activeSplitIndex)
                : $this->total
        );
        $this->showQrisPreviewModal = true;
    }

    public function closeQrisPreviewModal()
    {
        $this->showQrisPreviewModal = false;
        $this->previewQrisAmount = null;
    }

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

        if ($this->activeShift->status !== ShiftStatus::Open) {
            $this->addError('cashMovementAmount', 'Shift sudah ditutup.');
            return;
        }

        CashMovement::create([
            'shift_id' => $this->activeShift->id,
            'type' => $this->cashMovementType,
            'amount' => (float) $this->cashMovementAmount,
            'reason' => $this->cashMovementReason,
            'created_by' => Auth::id(),
        ]);

        $this->showCashMovementModal = false;
    }

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

        try {
            $this->shiftService->close($this->activeShift, (float) $this->endShiftActualCash, $this->endShiftNote ?: null);
        } catch (\Exception $e) {
            $this->addError('endShiftActualCash', $e->getMessage());
            return;
        }

        $this->activeShift = null;
        $this->showEndShiftModal = false;
    }

    public function openQrisPreviewForOrder(int $id): void
    {
        $order = Order::findOrFail($id);
        $this->previewQrisAmount = (int) round($order->total_amount);
        $this->showQrisPreviewModal = true;
    }

    #[On('order-placed')]
    public function refreshTableOrders(): void
    {
        // no-op: re-render alone re-evaluates the (uncached) computed order lists below
    }

    #[Computed]
    public function fromTableOrders()
    {
        return Order::with(['table', 'items.product'])->where('status', 'pending')->whereNot('table_id', NULL)->get();
    }

    public function acceptTableOrder(int $id): void
    {
        $order = Order::with('payment')->findOrFail($id);
        $paymentMethod = $order->payment->payment_method;

        if ($paymentMethod === 'qris' && ! ($this->confirmedPayments[$id] ?? false)) {
            return;
        }

        $this->orderService->acceptOrder($id);

        unset($this->confirmedPayments[$id]);
    }

    public function declineTableOrder(int $id)
    {
        $this->orderService->declineOrder($id);

        return redirect()->back()->with('message', 'Pesanan berhasil ditolak!')->with('type', 'success');
    }

    #[Computed]
    public function processingTableOrders()
    {
        return Order::with(['table', 'items.product'])
            ->where('status', OrderStatus::Processing)
            ->whereNot('table_id', null)
            ->get();
    }

    public function markOrderReady(int $id): void
    {
        $this->orderService->markReady($id);
    }

    #[Computed]
    public function readyTableOrders()
    {
        return Order::with(['table', 'payment', 'items.product'])
            ->where('status', OrderStatus::Ready)
            ->whereNot('table_id', null)
            ->get();
    }

    public function finalizeTableOrder(int $id): void
    {
        $order = Order::with(['payment', 'items.product'])->findOrFail($id);

        $this->cart = $order->items->map(fn ($item) => [
            'id' => $item->product_id,
            'name' => $item->product->name ?? 'Produk dihapus',
            'price' => $item->price,
            'qty' => $item->quantity,
        ])->toArray();

        $this->currentOrderId = $order->id;
        $this->paymentMethod = $order->payment->payment_method;
        $this->paymentConfirmed = $this->paymentMethod !== 'cash';
        $this->orderType = $order->order_type;
        $this->showPaymentModal = true;
    }

    public function updatedSearch()
    {
        $this->loadProducts();
    }

    public function loadProducts()
    {
        $query = Product::where('is_active', true);
        
        if ($this->selectedCategory) {
            $query->where('category_id', $this->selectedCategory);
        }

        if (!empty($this->search)) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }
        
        $this->products = $query->get();
    }

    public function filterCategory($categoryId = null)
    {
        $this->selectedCategory = $categoryId;
        $this->loadProducts();
    }    

    public function checkout()
    {
        if (empty($this->cart) || empty($this->customerName)) return;

        $order = $this->orderService->processOrder($this->cart, null, $this->customerName, $this->orderType, $this->activeDraft, shiftId: $this->activeShift?->id);

        $this->currentOrderId = $order->id;
        $this->showPaymentModal = true;
    }

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
                    null,
                    shiftId: $this->activeShift?->id
                );

                $this->splitGroups[$index]['order_id'] = $order->id;
                $this->splitGroups[$index]['paid'] = false;
            }
        });

        $this->activeDraft = null;
        $this->switchSplitTab(0);
        $this->showPaymentModal = true;
    }

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

    public function finalizeOrder()
    {
        $this->ensureActivePaymentMethod();

        if ($this->paymentMethod === 'cash' && empty($this->cashReceived)) return;
        if ($this->paymentMethod !== 'cash' && ! $this->paymentConfirmed) return;

        if ($this->activeSplitIndex !== null && ($this->splitGroups[$this->activeSplitIndex]['paid'] ?? false)) {
            return;
        }

        $order = $this->orderService->finalizeOrder($this->currentOrderId, $this->paymentMethod, $this->cashReceived, $this->orderType, shiftId: $this->activeShift?->id);

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

    public function resetCashier()
    {
        $this->reset(['cart', 'customerName', 'paymentMethod', 'paymentConfirmed', 'cashReceived', 'currentOrderId', 'orderType']);
        $this->ensureActivePaymentMethod();
    }

    private function findCustomerIdByName($name)
    {
        $customer = Customer::where('name', $name)->first();
        return $customer ? $customer->id : null;
    }

    public function closePaymentModal()
    {
        if ($this->activeSplitIndex !== null) {
            $this->resetCashier();
            $this->reset(['splitGroups', 'splitMode', 'activeSplitIndex']);
        }

        $this->showPaymentModal = false;
        $this->showQrisPreviewModal = false;
        $this->previewQrisAmount = null;
    }

    public function showReceipt($orderId)
    {
        return redirect()->route('cashier.receipt', ['orderId' => $orderId]);
    }

    public function render()
    {
        return view('livewire.pos.cashier');
    }
}
