<?php

namespace App\Traits;

use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use App\Services\Cart\CartCalculatorService;

trait CartCalculation
{
    #[Locked]
    public array $cart = [];
    public float $taxRate = 0.11;
    public float $discount = 0;
    public ?int $cashReceived = null;
    #[Locked]
    public bool $splitMode = false;
    #[Locked]
    public array $splitGroups = [];
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
                $productId = $this->cart[$key]['id'];
                $this->cart[$key]['qty']--;
                $this->syncCart($key);
                $this->trimAssignmentsForProduct($productId);
            } else {
                $this->removeFromCart($key);
            }
        }
    }

    public function removeFromCart($index)
    {
        $productId = $this->cart[$index]['id'] ?? null;

        unset($this->cart[$index]);
        $this->cart = array_values($this->cart); // re-index

        if ($productId !== null) {
            $this->clearSplitAssignmentsForProduct($productId);
        }
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

    public function toggleSplitMode(): void
    {
        $this->splitMode = ! $this->splitMode;

        if (! $this->splitMode) {
            $this->splitGroups = [];
        }
    }

    public function addSplitGroup(string $name): void
    {
        $name = trim($name);

        if ($name === '') {
            return;
        }

        $this->splitGroups[] = ['name' => $name, 'assignments' => []];
    }

    public function removeSplitGroup(int $groupIndex): void
    {
        unset($this->splitGroups[$groupIndex]);
        $this->splitGroups = array_values($this->splitGroups);
    }

    public function assignUnitToGroup(int $groupIndex, int $productId): void
    {
        if (! isset($this->splitGroups[$groupIndex]) || $this->unassignedQty($productId) <= 0) {
            return;
        }

        $current = $this->splitGroups[$groupIndex]['assignments'][$productId] ?? 0;
        $this->splitGroups[$groupIndex]['assignments'][$productId] = $current + 1;
    }

    public function unassignUnitFromGroup(int $groupIndex, int $productId): void
    {
        if (! isset($this->splitGroups[$groupIndex]['assignments'][$productId])) {
            return;
        }

        $remaining = $this->splitGroups[$groupIndex]['assignments'][$productId] - 1;

        if ($remaining <= 0) {
            unset($this->splitGroups[$groupIndex]['assignments'][$productId]);
        } else {
            $this->splitGroups[$groupIndex]['assignments'][$productId] = $remaining;
        }
    }

    public function unassignedQty(int $productId): int
    {
        $cartItem = $this->findCartItem($productId);

        if (! $cartItem) {
            return 0;
        }

        $assigned = 0;

        foreach ($this->splitGroups as $group) {
            $assigned += $group['assignments'][$productId] ?? 0;
        }

        return max(0, $cartItem['qty'] - $assigned);
    }

    public function splitGroupSubtotal(int $groupIndex): float
    {
        if (! isset($this->splitGroups[$groupIndex])) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($this->splitGroups[$groupIndex]['assignments'] as $productId => $qty) {
            $cartItem = $this->findCartItem($productId);

            if ($cartItem) {
                $total += $cartItem['price'] * $qty;
            }
        }

        return $total;
    }

    public function canCheckoutSplit(): bool
    {
        if (empty($this->cart) || count($this->splitGroups) < 2) {
            return false;
        }

        foreach ($this->splitGroups as $group) {
            if (trim($group['name']) === '' || empty($group['assignments'])) {
                return false;
            }
        }

        foreach ($this->cart as $item) {
            if ($this->unassignedQty($item['id']) > 0) {
                return false;
            }
        }

        return true;
    }

    public function buildSplitCartItems(int $groupIndex): array
    {
        if (! isset($this->splitGroups[$groupIndex])) {
            return [];
        }

        $items = [];

        foreach ($this->splitGroups[$groupIndex]['assignments'] as $productId => $qty) {
            $cartItem = $this->findCartItem($productId);

            if (! $cartItem || $qty <= 0) {
                continue;
            }

            $items[] = [
                'id' => $cartItem['id'],
                'name' => $cartItem['name'],
                'price' => $cartItem['price'],
                'qty' => $qty,
                'subtotal' => $cartItem['price'] * $qty,
            ];
        }

        return $items;
    }

    private function clearSplitAssignmentsForProduct(int $productId): void
    {
        foreach ($this->splitGroups as $index => $group) {
            unset($this->splitGroups[$index]['assignments'][$productId]);
        }
    }

    private function trimAssignmentsForProduct(int $productId): void
    {
        $cartItem = $this->findCartItem($productId);

        if (! $cartItem) {
            return;
        }

        $newQty = $cartItem['qty'];
        $assigned = 0;

        foreach ($this->splitGroups as $group) {
            $assigned += $group['assignments'][$productId] ?? 0;
        }

        if ($assigned <= $newQty) {
            return;
        }

        $toRemove = $assigned - $newQty;

        foreach ($this->splitGroups as $index => $group) {
            if ($toRemove <= 0) {
                break;
            }

            if (isset($this->splitGroups[$index]['assignments'][$productId])) {
                $currentAssignment = $this->splitGroups[$index]['assignments'][$productId];
                $removeFromThisGroup = min($currentAssignment, $toRemove);
                $remaining = $currentAssignment - $removeFromThisGroup;

                if ($remaining <= 0) {
                    unset($this->splitGroups[$index]['assignments'][$productId]);
                } else {
                    $this->splitGroups[$index]['assignments'][$productId] = $remaining;
                }

                $toRemove -= $removeFromThisGroup;
            }
        }
    }

    private function findCartItem(int $productId): ?array
    {
        return collect($this->cart)->firstWhere('id', $productId);
    }

    protected function resolveProduct(int $productId)
    {
        return Product::find($productId);
    }
}