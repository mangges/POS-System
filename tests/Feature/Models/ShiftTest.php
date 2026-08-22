<?php

namespace Tests\Feature\Models;

use App\Enum\Orders\OrderStatus;
use App\Enum\Shifts\CashMovementType;
use App\Enum\Shifts\ShiftStatus;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_can_be_created_with_expected_casts(): void
    {
        $user = User::factory()->create();

        $shift = Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 100000,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);

        $this->assertInstanceOf(ShiftStatus::class, $shift->status);
        $this->assertSame(ShiftStatus::Open, $shift->status);
        $this->assertSame(100000.0, (float) $shift->opening_cash);
        $this->assertTrue($shift->user->is($user));
    }

    public function test_shift_has_many_cash_movements(): void
    {
        $user = User::factory()->create();
        $shift = Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 0,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);

        $movement = CashMovement::create([
            'shift_id' => $shift->id,
            'type' => CashMovementType::In,
            'amount' => 50000,
            'reason' => 'Setoran modal tambahan',
            'created_by' => $user->id,
        ]);

        $this->assertInstanceOf(CashMovementType::class, $movement->type);
        $this->assertTrue($shift->cashMovements->first()->is($movement));
        $this->assertTrue($movement->shift->is($shift));
        $this->assertTrue($movement->creator->is($user));
    }

    public function test_shift_has_many_orders_via_shift_id(): void
    {
        $user = User::factory()->create();
        $shift = Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 0,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);

        $order = Order::create([
            'order_number' => 'ORD-SHIFT-1',
            'total_amount' => 10000,
            'tax' => 0,
            'discount' => 0,
            'status' => OrderStatus::Completed,
            'order_type' => 'dine_in',
            'shift_id' => $shift->id,
        ]);

        $this->assertTrue($shift->orders->first()->is($order));
        $this->assertTrue($order->shift->is($shift));
    }
}
