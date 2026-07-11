<?php

namespace App\Livewire\Pos;

use App\Models\Category;
use App\Models\Product;
use App\Traits\CartCalculation;
use Livewire\Component;
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

    use CartCalculation;

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
        if (empty($this->cart)) return;

        // Implement checkout logic here (insert to orders, order_items, decrement stock)
        
        $this->cart = []; // clear cart after successful checkout
        session()->flash('message', 'Pesanan berhasil diproses!');
    }

    public function render()
    {
        return view('livewire.pos.cashier');
    }
}
