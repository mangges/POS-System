<?php

namespace App\Livewire\Pos;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

use App\Models\Category;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Order;
use App\Enum\Orders\OrderStatus;
use App\Traits\CartCalculation;
use App\Services\Order\OrderService;
use App\Traits\PaymentMethodSelection;

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

    public $activeDraft = null;
    
    public $showPaymentModal = false;
    public $showDraftsModal = false;
    public $showFromTableModal = false;
    public $showKitchenOrdersModal = false;
    public $showQrisPreviewModal = false;
    public ?int $previewQrisAmount = null;

    use CartCalculation {
        addToCart as protected traitAddToCart;
    }
    use PaymentMethodSelection;

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
        $this->reset('cart');
    }

    public function saveDraft()
    {
        if (empty($this->cart)) return;

        $this->orderService->processOrder($this->cart, null, $this->customerName, $this->orderType, $this->activeDraft);

        $this->activeDraft = null;
        $this->reset('cart', 'customerName');
        return redirect()->route('filament.admin.pages.cashier')->with('message', 'Data berhasil disimpan!')->with('type', 'success');
    }

    public function deleteDraft(int $id)
    {
        DB::transaction(function () use ($id) {
            $order = Order::findOrFail($id);
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
        $this->showQrisPreviewModal = true;
    }

    public function closeQrisPreviewModal()
    {
        $this->showQrisPreviewModal = false;
        $this->previewQrisAmount = null;
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

    public function getUserInitialsProperty()
    {
        $name = Auth::check() ? Auth::user()->name : 'Cashier Name';
        $words = explode(' ', $name);
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($name, 0, 2));
    }

    public function filterCategory($categoryId = null)
    {
        $this->selectedCategory = $categoryId;
        $this->loadProducts();
    }    

    public function checkout()
    {
        if (empty($this->cart) || empty($this->customerName)) return;

        $order = $this->orderService->processOrder($this->cart, null, $this->customerName, $this->orderType, $this->activeDraft);
        
        $this->currentOrderId = $order->id;
        $this->showPaymentModal = true;
    }

    public function finalizeOrder()
    {
        $this->ensureActivePaymentMethod();

        if ($this->paymentMethod === 'cash' && empty($this->cashReceived)) return;
        if ($this->paymentMethod !== 'cash' && ! $this->paymentConfirmed) return;

        $order = $this->orderService->finalizeOrder($this->currentOrderId, $this->paymentMethod, $this->cashReceived, $this->orderType);
        $this->resetCashier();
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
