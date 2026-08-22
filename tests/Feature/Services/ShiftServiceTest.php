<?php

namespace Tests\Feature\Services;

use App\Enum\Orders\OrderStatus;
use App\Enum\Orders\PaymentStatus;
use App\Enum\Payments\PaymentMethod;
use App\Enum\Shifts\CashMovementType;
use App\Enum\Shifts\ShiftStatus;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\User;
use App\Services\Shift\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrderWithPayment(int $shiftId, string $paymentMethod, string $paymentStatus, float $amount): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'total_amount' => $amount,
            'tax' => 0,
            'discount' => 0,
            'status' => OrderStatus::Completed,
            'order_type' => 'dine_in',
            'shift_id' => $shiftId,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'payment_method' => $paymentMethod,
            'amount' => $amount,
            'status' => $paymentStatus,
        ]);

        return $order;
    }

    public function test_open_creates_an_open_shift(): void
    {
        $user = User::factory()->create();

        $shift = (new ShiftService())->open($user->id, 150000);

        $this->assertSame($user->id, $shift->user_id);
        $this->assertSame(150000.0, (float) $shift->opening_cash);
        $this->assertSame(ShiftStatus::Open, $shift->status);
        $this->assertNotNull($shift->opened_at);
    }

    public function test_open_fails_when_user_already_has_an_open_shift(): void
    {
        $user = User::factory()->create();
        (new ShiftService())->open($user->id, 100000);

        $this->expectException(\Exception::class);

        (new ShiftService())->open($user->id, 50000);
    }

    public function test_close_computes_expected_cash_from_cash_sales_and_movements_only(): void
    {
        $user = User::factory()->create();
        $shift = (new ShiftService())->open($user->id, 100000);

        $this->makeOrderWithPayment($shift->id, PaymentMethod::Cash->value, PaymentStatus::Success->value, 60000);
        $this->makeOrderWithPayment($shift->id, PaymentMethod::Qris->value, PaymentStatus::Success->value, 40000);
        $this->makeOrderWithPayment($shift->id, PaymentMethod::Cash->value, PaymentStatus::Pending->value, 999999);

        CashMovement::create([
            'shift_id' => $shift->id,
            'type' => CashMovementType::In,
            'amount' => 20000,
            'reason' => 'Tambah modal',
            'created_by' => $user->id,
        ]);
        CashMovement::create([
            'shift_id' => $shift->id,
            'type' => CashMovementType::Out,
            'amount' => 5000,
            'reason' => 'Setor ke bank',
            'created_by' => $user->id,
        ]);

        $closed = (new ShiftService())->close($shift, 175500, 'Selisih Rp 500 karena kembalian kurang.');

        // expected = opening 100000 + cash sales 60000 + cash in 20000 - cash out 5000 = 175000
        $this->assertSame(175000.0, (float) $closed->expected_cash);
        $this->assertSame(175500.0, (float) $closed->actual_cash);
        $this->assertSame(500.0, (float) $closed->difference);
        $this->assertSame(ShiftStatus::Closed, $closed->status);
        $this->assertNotNull($closed->closed_at);
        $this->assertSame('Selisih Rp 500 karena kembalian kurang.', $closed->note);
    }
}
