<?php

namespace App\Livewire\LandingPage;

use App\Models\Category;
use App\Models\GuestSession;
use App\Models\Product;
use App\Models\Table;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Enum\Orders\OrderStatus;
use App\Models\Order;
use App\Traits\CartCalculation;
use App\Services\Order\OrderService;
use App\Traits\PaymentMethodSelection;
use Illuminate\Support\Facades\DB;

class LandingPage extends Component
{
    #[Url]
    public $table;

    public ?int $guestSessionId = null;

    public $search = '';
    public $selectedCategory = null;
    public $selectedProduct = null;
    public ?int $currentOrderId = null;

    public $showPaymentModal = false;
    public string $customerName = '';
    public string $orderType = 'dine-in';
    public string $paymentMethod = 'cash';
    public bool $orderSubmitted = false;
    public ?string $lastOrderNumber = null;
    public ?int $lastOrderTotal = null;
    public array $splitOrderDetails = [];
    public ?int $activeSuccessTabIndex = null;

    public ?int $viewingOrderId = null;

    protected OrderService $orderService;

    use CartCalculation;
    use PaymentMethodSelection;

    public function boot(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function mount(string $session_token)
    {
        $guestSession = GuestSession::where('token', $session_token)->firstOrFail();

        if ($guestSession->isExpired()) {
            abort(410, 'Session expired. Please scan the QR code again.');
        }

        $this->guestSessionId = $guestSession->id;
        $this->table = $guestSession->qrCode->table;
        $this->ensureActivePaymentMethod();
    }

    public function setCategory($categoryId)
    {
        $this->selectedCategory = $categoryId;
    }

    public function selectProduct($productId)
    {
        $this->selectedProduct = Product::find($productId);
    }

    public function closeProductModal()
    {
        $this->selectedProduct = null;
    }

    public function openPaymentModal()
    {
        if ($this->splitMode) {
            if (! $this->canCheckoutSplit()) return;
        } elseif (empty($this->cart)) {
            return;
        }

        $this->showPaymentModal = true;
    }

    public function switchSuccessTab(int $index): void
    {
        if (! isset($this->splitOrderDetails[$index])) {
            return;
        }

        $this->activeSuccessTabIndex = $index;
    }

    public function closePaymentModal()
    {
        $this->showPaymentModal = false;

        if ($this->orderSubmitted) {
            $this->reset(['customerName', 'paymentMethod', 'orderSubmitted', 'lastOrderNumber', 'lastOrderTotal', 'currentOrderId', 'splitOrderDetails', 'activeSuccessTabIndex']);
            $this->ensureActivePaymentMethod();
        }
    }

    #[On('order-status-updated')]
    public function handleOrderStatusUpdated($orderId, $status)
    {
        if (!$this->orderSubmitted || (int) $orderId !== $this->currentOrderId) {
            return;
        }

        if ($status === OrderStatus::Completed->value) {
            $token = Order::find($orderId)?->token;

            if ($token) {
                return $this->redirect(route('receipt.show', ['token' => $token]));
            }
        }

        if ($status === OrderStatus::Cancelled->value) {
            $this->closePaymentModal();
        }
    }

    #[On('show-order-detail')]
    public function showOrderDetail($orderId): void
    {
        $order = Order::where('id', (int) $orderId)->where('table_id', $this->table->id)->first();

        if (! $order) {
            return;
        }

        if ($order->status === OrderStatus::Completed) {
            if ($order->token) {
                $this->redirect(route('receipt.show', ['token' => $order->token]));
            }
            return;
        }

        $this->viewingOrderId = $order->id;
    }

    public function closeOrderDetailModal(): void
    {
        $this->viewingOrderId = null;
    }

    #[Computed]
    public function viewingOrder(): ?Order
    {
        if (! $this->viewingOrderId) {
            return null;
        }

        return Order::with(['items.product', 'payment'])
            ->where('table_id', $this->table->id)
            ->find($this->viewingOrderId);
    }

    public function checkout()
    {
        if (empty($this->cart) || empty($this->customerName)) return;

        $guestSession = GuestSession::findOrFail($this->guestSessionId);

        if ($guestSession->isExpired()) {
            abort(410, 'Session expired. Please scan the QR code again.');
        }

        $this->ensureActivePaymentMethod();

        $order = $this->orderService->processOrder(
            $this->cart,
            $guestSession->qrCode->table_id,
            $this->customerName,
            $this->orderType,
            null,
            $this->paymentMethod
        );

        $this->currentOrderId = $order->id;
        $this->lastOrderNumber = $order->order_number;
        $this->lastOrderTotal = (int) round($order->total_amount);
        $this->orderSubmitted = true;
        $this->reset('cart');
        $this->dispatch('order-placed', orderId: $order->id);
    }

    public function checkoutSplit(): void
    {
        if (! $this->canCheckoutSplit()) return;

        $guestSession = GuestSession::findOrFail($this->guestSessionId);

        if ($guestSession->isExpired()) {
            abort(410, 'Session expired. Please scan the QR code again.');
        }

        $this->ensureActivePaymentMethod();

        $orderDetails = [];

        DB::transaction(function () use ($guestSession, &$orderDetails) {
            foreach ($this->splitGroups as $index => $group) {
                $order = $this->orderService->processOrder(
                    $this->buildSplitCartItems($index),
                    $guestSession->qrCode->table_id,
                    $group['name'],
                    $this->orderType,
                    null,
                    $this->paymentMethod
                );

                $orderDetails[] = [
                    'name' => $group['name'],
                    'order_number' => $order->order_number,
                    'total' => (int) round($order->total_amount),
                ];
                $this->dispatch('order-placed', orderId: $order->id);
            }
        });

        $this->splitOrderDetails = $orderDetails;
        $this->activeSuccessTabIndex = 0;
        $this->orderSubmitted = true;
        $this->reset(['cart', 'splitGroups', 'splitMode']);
    }

    public function render()
    {
        $query = Product::where('is_active', true);

        if ($this->selectedCategory) {
            $query->where('category_id', $this->selectedCategory);
        }

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        $products = $query->get();
        $categories = Category::all();

        return view('livewire.landing-page.landing-page', [
            'products' => $products,
            'categories' => $categories,
        ])->layout('components.layouts.app');
    }
}
