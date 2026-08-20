<?php

namespace App\Traits;

use App\Models\Product;
use Livewire\Attributes\Computed;
use App\Services\Cart\CartCalculatorService;

trait CartCalculation
{
    public array $cart = [];
    public float $taxRate = 0.11;
    public float $discount = 0;
    public ?int $cashReceived = null;
    protected CartCalculatorService $cartCalculatorService;

    public function bootCartCalculation(CartCalculatorService $cartCalculatorService)
    {
        $this->cartCalculatorService = $cartCalculatorService;
    }
    
    public function addToCart($productId)
    {
        $product = $this->resolveProduct($productId);
        
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
            $this->syncCart($cartIndex);
        } else {
            $this->cart[] = [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'qty' => 1,
                'subtotal' => $product->price,
                'image' => $product->image,
            ];
        }
    }

    public function incrementQuantity($key)
    {
        if (isset($this->cart[$key])) {
            $this->cart[$key]['qty']++;

            $this->syncCart($key);
        }
    }

    public function decrementQuantity($key)
    {
        if (isset($this->cart[$key])) {
            if ($this->cart[$key]['qty'] > 1) {
                $this->cart[$key]['qty']--;
                $this->syncCart($key);
            } else {
                $this->removeFromCart($key);
            }
        }
    }

    public function removeFromCart($index)
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart); // re-index
    }
    
    #[Computed]
    public function subtotal()
    {
        return $this->cartCalculatorService->subtotal($this->cart);
    }
    
    #[Computed]
    public function taxAmount()
    {
        return $this->cartCalculatorService->tax($this->subtotal);
    }
    
    #[Computed]
    public function total()
    {
        return $this->cartCalculatorService->total($this->subtotal, $this->taxAmount);
    }

    #[Computed]
    public function change()
    {
        if ($this->cashReceived === null) {
            return null;
        }

        $change = $this->cashReceived - $this->total;

        return $change >= 0 ? $change : 0;
    }

    public function syncCart($key)
    {
        return $this->cartCalculatorService->syncCart($this->cart, $key);
    }

    protected function resolveProduct(int $productId)
    {
        return Product::find($productId);
    }
}