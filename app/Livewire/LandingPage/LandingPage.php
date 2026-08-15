<?php

namespace App\Livewire\LandingPage;

use App\Models\Category;
use App\Models\Product;
use App\Models\Table;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Traits\CartCalculation;
use App\Services\Order\OrderService;

class LandingPage extends Component
{
    #[Url]
    public $table;

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

    protected OrderService $orderService;

    use CartCalculation;

    public function boot(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function mount(string $table_token)
    {
        $this->table = Table::where('qr_token', $table_token)->firstOrFail();
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
        if (empty($this->cart)) return;

        $this->showPaymentModal = true;
    }

    public function closePaymentModal()
    {
        $this->showPaymentModal = false;

        if ($this->orderSubmitted) {
            $this->reset(['customerName', 'paymentMethod', 'orderSubmitted', 'lastOrderNumber', 'currentOrderId']);
        }
    }

    public function checkout()
    {
        if (empty($this->cart) || empty($this->customerName)) return;

        $order = $this->orderService->processOrder(
            $this->cart,
            $this->table->id,
            $this->customerName,
            $this->orderType,
            null,
            $this->paymentMethod
        );

        $this->currentOrderId = $order->id;
        $this->lastOrderNumber = $order->order_number;
        $this->orderSubmitted = true;
        $this->reset('cart');
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
