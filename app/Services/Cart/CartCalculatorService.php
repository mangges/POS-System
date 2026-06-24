<?php
namespace App\Services\Cart;


class CartCalculatorService 
{
    public function __construct(
        private float $taxRate = 0.11
    ) {}

    public function subtotal(array $items): float
    {
        return collect($items)->sum(fn($i) => $i['price'] * $i['qty']);
    }

    public function tax(float $subtotal): float
    {
        return $subtotal * $this->taxRate;
    }

    //Do Calculate here if any other property e.g Discount, Voucher
    public function total(float $subtotal, float $tax): float
    {
        return $subtotal + $tax;
    }

    public function syncCart($cart, $key)
    {
        return $cart[$key]['subtotal'] = $cart[$key]['qty'] * $cart[$key]['price'];
    }
}