<?php

namespace App\Livewire\Pos;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

use App\Models\Category;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ReceiptSetting;

#[Layout('components.layouts.receipt')]
class Receipt extends Component
{
    public ?string $token = null;
    public ?int $orderId = null;

    public function mount($token = null, $orderId = null)
    {
        $this->token = $token;
        $this->orderId = $orderId;
    }

    #[Computed]
    public function order()
    {
        $query = Order::with(['items.product', 'payment', 'table']);

        return $this->orderId
            ? $query->findOrFail($this->orderId)
            : $query->where('token', $this->token)->firstOrFail();
    }

    #[Computed]
    public function orderItems()
    {
        return $this->order->items;
    }

    #[Computed]
    public function payment()
    {
        return $this->order->payment;
    }

    #[Computed]
    public function receiptSettings()
    {
        return ReceiptSetting::current();
    }

    #[Computed]
    public function subtotal()
    {
        return $this->orderItems->sum(function (OrderItem $item) {
            return $item->subtotal;
        });
    }

    #[Computed]
    public function change()
    {
        return $this->payment->amount - $this->order->total_amount;
    }

    #[Computed]
    public function savedOrders()
    {
        return Order::with('payment')
            ->where('status', 'completed')
            ->whereHas('payment', function ($query) {
                $query->where('status', 'success');
            })
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
    }

    public function render()
    {
        return view('livewire.pos.receipts.receipt');
    }
}