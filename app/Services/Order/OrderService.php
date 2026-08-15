<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\Cart\CartCalculatorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            $order = DB::transaction(function () use ($data) {
                $data['order_number'] = $this->createOrderNumber();
                $data['user_id'] = Auth::id();

                return Order::create($data);
            });
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

    public function storeOrderItem(int $orderId, array $cartItems, ?int $activeDraft = null): void
    {
        foreach ($cartItems as $item) {
            $subtotal = $this->cartCalculatorService->subtotal($item);

            $data = [
                "order_id" => $orderId,
                "product_id" => $item['id'],
                "quantity" => $item['qty'],
                "price" => $item['price'],
                "subtotal" => $subtotal,
                "notes" => $item['notes'] ?? null,
                "is_custom_price" => $item['is_custom_price'] ?? false,
            ];

            if ($activeDraft) {
                $orderItem = OrderItem::where('order_id', $orderId)
                    ->where('product_id', $item['id'])
                    ->first();

                if ($orderItem) {
                    $orderItem->update($data);
                    continue;
                } else {
                    OrderItem::create($data);
                }
            } else {
                OrderItem::create($data);
            }

        }
    }

    public function finalizeOrder(?int $orderId, string $paymentMethod, ?float $cashReceived = null, ?string $orderType = null): Order
    {
        $order = Order::find($orderId);
        $payment = $order->payment;
        $token = bin2hex(random_bytes(16));

        if ($paymentMethod === 'cash' && $cashReceived < $order->total_amount) {
            throw new \Exception('Cash received is less than the total amount.');
        }

        $order->update([
            'status' => OrderStatus::Completed,
            'payment_id' => $payment->id,
            'payment_method' => $paymentMethod,
            'order_type' => $orderType,
            'token' => $token,
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
        $lastOrder = Order::latest('id')->lockForUpdate()->first();
        $lastOrderNumber = $lastOrder ? (int) substr($lastOrder->order_number, 3) : 0;
        $newOrderNumber = str_pad($lastOrderNumber + 1, 6, '0', STR_PAD_LEFT);
        return 'ORD' . $newOrderNumber;
    }
}