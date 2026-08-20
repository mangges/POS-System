<?php 

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class OrderService
{
    protected static ?float $taxAmount = 0.11;
  
    public static function placeOrder(array $items, array $customerInfo = [], string $paymentMethod = 'cash', string $orderType = 'dine-in', int $tableId = null)
    {
        return DB::transaction(function () use ($items, $customerInfo, $paymentMethod, $orderType, $tableId) {
            $pricing = self::calculatePrice($items);
            $orderNumber = self::generateOrderNumber();

            $order = Order::create([
                'order_number' => $orderNumber,
                'customer_name' => $customerInfo['name'] ?? 'Guest',
                'tax' => $pricing['tax_amount'],
                'discount' => $pricing['discount'],
                'total_amount' => $pricing['total'],
                'status' => 'completed',
                'payment_status' => 'paid',
                'order_type' => $orderType
            ]);

            if($orderType === 'dine-in' && $tableId) {
                Order::where('id', $order->id)->update([
                    'table_id' => $tableId,
                ]);
            }

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['id'],
                    'quantity' => $item['qty'],
                    'price' => $item['price'],
                    'subtotal' => $item['qty'] * $item['price'],
                ]);
            }

            self::syncProductStock($items);

            return $order;
        });
    }

    private static function calculatePrice(array $items)
    {
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['qty'] * $item['price'];
        }

        $taxAmount = $subtotal * self::$taxAmount;
        $discount = 0;
        $total = $subtotal + $taxAmount - $discount;

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount' => $discount,
            'total' => $total,
        ];
    }

    private static function syncProductStock(array $items)
    {
        foreach ($items as $item) {
            $product = Product::find($item['id']);
            if (!$product->has_recipe) {
                $product->decrement('stock', $item['qty']);
            }
        }
    }
    
    private static function generateOrderNumber()
    {
        $latestOrder = Order::latest('created_at')->first();
        $lastNumber = 0;
        if ($latestOrder && str_starts_with($latestOrder->order_number, 'INV-')) {
            $lastNumber = (int) substr($latestOrder->order_number, 4);
        }
        return 'INV-' . str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
    }
}
