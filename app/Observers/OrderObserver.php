<?php

namespace App\Observers;

use App\Enum\Orders\OrderStatus;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status') || $order->status !== OrderStatus::Completed) {
            return;
        }

        DB::transaction(function () use ($order) {
            foreach ($order->items()->with('product.recipes')->get() as $item) {
                $product = $item->product;

                if ($product->has_recipe) {
                    foreach ($product->recipes as $recipe) {
                        StockMovement::create([
                            'reference_id' => $recipe->raw_material_id,
                            'reference_type' => 'raw_material',
                            'type' => 'out',
                            'quantity' => $recipe->quantity * $item->quantity,
                            'user_id' => $order->user_id,
                            'notes' => "Order {$order->order_number}",
                        ]);
                    }
                } else {
                    StockMovement::create([
                        'reference_id' => $product->id,
                        'reference_type' => 'product',
                        'type' => 'out',
                        'quantity' => $item->quantity,
                        'user_id' => $order->user_id,
                        'notes' => "Order {$order->order_number}",
                    ]);
                }
            }
        });
    }
}
