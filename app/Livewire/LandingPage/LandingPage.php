<?php

namespace App\Livewire\LandingPage;

use App\Models\Category;
use App\Models\Product;
use App\Models\Table;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Traits\CartCalculation;

class LandingPage extends Component
{
    #[Url]
    public $table;

    public $search = '';
    public $selectedCategory = null;
    public $selectedProduct = null;

    use CartCalculation;

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
