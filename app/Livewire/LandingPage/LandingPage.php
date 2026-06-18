<?php

namespace App\Livewire\LandingPage;

use App\Models\Category;
use App\Models\Product;
use Livewire\Attributes\Url;
use Livewire\Component;

class LandingPage extends Component
{
    #[Url]
    public $table = '';

    public $search = '';
    public $selectedCategory = null;

    public $cart = [];
    public $selectedProduct = null;

    public function mount()
    {
        $this->table = request()->query('table', '');
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

    public function addToCart($productId)
    {
        $product = Product::find($productId);
        if (!$product) return;

        $existingItemKey = null;
        foreach ($this->cart as $key => $item) {
            if ($item['product_id'] == $productId) {
                $existingItemKey = $key;
                break;
            }
        }

        if ($existingItemKey !== null) {
            $this->cart[$existingItemKey]['quantity']++;
        } else {
            $this->cart[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'image' => $product->image,
                'quantity' => 1,
            ];
        }
    }

    public function incrementQuantity($key)
    {
        if (isset($this->cart[$key])) {
            $this->cart[$key]['quantity']++;
        }
    }

    public function decrementQuantity($key)
    {
        if (isset($this->cart[$key])) {
            if ($this->cart[$key]['quantity'] > 1) {
                $this->cart[$key]['quantity']--;
            } else {
                unset($this->cart[$key]);
                $this->cart = array_values($this->cart); // Re-index array
            }
        }
    }

    public function removeFromCart($key)
    {
        if (isset($this->cart[$key])) {
            unset($this->cart[$key]);
            $this->cart = array_values($this->cart);
        }
    }

    public function getCartTotalProperty()
    {
        $total = 0;
        foreach ($this->cart as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        return $total;
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
