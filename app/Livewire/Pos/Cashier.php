<?php

namespace App\Livewire\Pos;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;

#[Layout('components.layouts.app')]
class Cashier extends Component
{
    public $categories = [];
    public $products = [];
    public $selectedCategory = null;
    
    public $cart = [];
    public $taxRate = 0.11; // 11% Tax
    public $discount = 0;
    
    public $search = '';

    public function mount()
    {
        $this->categories = DB::table('categories')->get();
        $this->loadProducts();
    }

    public function updatedSearch()
    {
        $this->loadProducts();
    }

    public function loadProducts()
    {
        $query = DB::table('products')->where('is_active', true);
        
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
        $name = auth()->check() ? auth()->user()->name : 'Cashier Name';
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

    public function addToCart($productId)
    {
        $product = collect($this->products)->firstWhere('id', $productId);
        
        if (!$product || $product->is_out_of_stock) {
            return;
        }

        // If not recipe based, check stock
        if (!$product->has_recipe && $product->stock !== null && $product->stock <= 0) {
            return;
        }

        $cartIndex = collect($this->cart)->search(fn($item) => $item['id'] === $productId);

        if ($cartIndex !== false) {
            $this->cart[$cartIndex]['qty']++;
            $this->cart[$cartIndex]['subtotal'] = $this->cart[$cartIndex]['qty'] * $this->cart[$cartIndex]['price'];
        } else {
            $this->cart[] = [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'qty' => 1,
                'subtotal' => $product->price,
            ];
        }
    }

    public function updateQty($index, $action)
    {
        if (!isset($this->cart[$index])) return;

        if ($action === 'increase') {
            $this->cart[$index]['qty']++;
        } elseif ($action === 'decrease') {
            if ($this->cart[$index]['qty'] > 1) {
                $this->cart[$index]['qty']--;
            } else {
                $this->removeFromCart($index);
                return;
            }
        }
        
        $this->cart[$index]['subtotal'] = $this->cart[$index]['qty'] * $this->cart[$index]['price'];
    }

    public function removeFromCart($index)
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart); // re-index
    }
    
    public function getSubtotalProperty()
    {
        return collect($this->cart)->sum('subtotal');
    }
    
    public function getTaxAmountProperty()
    {
        return $this->subtotal * $this->taxRate;
    }
    
    public function getTotalProperty()
    {
        return $this->subtotal + $this->taxAmount - $this->discount;
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
