<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Cart\CartCalculatorService;
use Illuminate\Support\Facades\Auth;
use App\Enum\Orders\OrderStatus;
use App\Enum\Orders\PaymentStatus;

class OrderService
{
    public function __construct(
        private CartCalculatorService $cartCalculatorService
    ) {}

    public function processOrder(array $cartItems, ?int $tableId = null, ?string $customerName = null, ?string $orderType = null, ?int $activeDraft = null): Order
    {
        $cartSubtotal = $this->cartCalculatorService->subtotal($cartItems);
        $cartTax = $this->cartCalculatorService->tax($cartSubtotal);
        $totalAmount = $this->cartCalculatorService->total($cartSubtotal, $cartTax);
        $data = [
            'table_id' => $tableId,
            'customer_id' => null,
            'customer_name' => $customerName,
            'total_amount' => $totalAmount,
            'tax' => $cartTax,
            'discount' => 0,
            'status' => OrderStatus::Pending,
            'payment_id' => null,
            'order_type' => $orderType,
        ];
        
        if ($activeDraft) {
            $order = Order::findOrFail($activeDraft);
            $order->update($data);
        } else {
            $additionalData = [
                'order_number' => $this->createOrderNumber(),
                'user_id' => Auth::id(),
            ];
            
            $order = Order::create(array_merge($data, $additionalData));
        }

        $this->storeOrderItem($order->id, $cartItems, $activeDraft);

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 0,
            'status' => PaymentStatus::Pending,
            'transaction_id' => null,
        ]);

        $order->update([
            'payment_id' => $payment->id,
        ]);

        return $order;
    }

    public function finalizeOrder(?int $orderId, string $paymentMethod, ?float $cashReceived = null, ?string $orderType = null): Order
    {
        $order = Order::find($orderId);
        $payment = $order->payment;

        if ($paymentMethod === 'cash' && $cashReceived < $order->total_amount) {
            throw new \Exception('Cash received is less than the total amount.');
        }
        $order->update([
            'order_status' => OrderStatus::Completed,
            'payment_id' => $payment->id,
            'payment_method' => $paymentMethod,
            'order_type' => $orderType,
        ]);

        $payment->update([
            'payment_method' => $paymentMethod,
            'amount' => $paymentMethod === 'cash' ? $cashReceived : 0,
            'status' => PaymentStatus::Success,
        ]);

        return $order;
    }

    private function createOrderNumber(): string
    {
        $lastOrder = Order::latest()->first();
        $lastOrderNumber = $lastOrder ? (int) substr($lastOrder->order_number, 3) : 0;
        $newOrderNumber = str_pad($lastOrderNumber + 1, 6, '0', STR_PAD_LEFT);
        return 'ORD' . $newOrderNumber;
    }
}