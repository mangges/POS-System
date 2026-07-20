<?php

namespace App\Livewire\Pos;

use App\Models\Category;
use App\Models\Product;
use App\Models\Customer;
use App\Traits\CartCalculation;
use Livewire\Component;
use App\Services\Order\OrderService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
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
    public $showPaymentModal = false;
    public string $paymentMethod = 'cash';
    public ?int $currentOrderId = null;
    public string $orderType = 'dine-in';

    use CartCalculation;

    public function boot(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function mount()
    {
        $this->categories = Category::all();
        $this->loadProducts();
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

        $order = $this->orderService->processOrder($this->cart, null, $this->customerName, $this->orderType);
        
        $this->currentOrderId = $order->id;
        $this->showPaymentModal = true;
    }

    public function finalizeOrder()
    {
        if ($this->paymentMethod === 'cash' && empty($this->cashReceived)) return;

        $this->orderService->finalizeOrder($this->currentOrderId, $this->paymentMethod, $this->cashReceived, $this->orderType);
        $this->resetCashier();
        $this->closePaymentModal();
    }

    public function resetCashier()
    {
        $this->reset(['cart', 'customerName', 'paymentMethod', 'cashReceived', 'currentOrderId', 'orderType']);
    }

    private function findCustomerIdByName($name)
    {
        $customer = Customer::where('name', $name)->first();
        return $customer ? $customer->id : null;
    }

    public function closePaymentModal()
    {
        $this->showPaymentModal = false;
    }

    public function render()
    {
        return view('livewire.pos.cashier');
    }
}
