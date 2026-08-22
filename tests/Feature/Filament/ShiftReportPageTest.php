<?php

namespace Tests\Feature\Filament;

use App\Enum\Orders\OrderStatus;
use App\Enum\Shifts\CashMovementType;
use App\Enum\Shifts\ShiftStatus;
use App\Filament\Pages\Reports\ShiftReport;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShiftReportPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeShiftWithSales(User $user, float $opening, float $cashSales, float $nonCashSales): Shift
    {
        $shift = Shift::create([
            'user_id' => $user->id,
            'opening_cash' => $opening,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);

        $order = Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'total_amount' => $cashSales,
            'tax' => 0,
            'discount' => 0,
            'status' => OrderStatus::Completed,
            'order_type' => 'dine_in',
            'shift_id' => $shift->id,
        ]);
        $payment = Payment::create(['order_id' => $order->id, 'payment_method' => 'cash', 'amount' => $cashSales, 'status' => 'success']);
        $order->update(['payment_id' => $payment->id]);

        $order2 = Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'total_amount' => $nonCashSales,
            'tax' => 0,
            'discount' => 0,
            'status' => OrderStatus::Completed,
            'order_type' => 'dine_in',
            'shift_id' => $shift->id,
        ]);
        $payment2 = Payment::create(['order_id' => $order2->id, 'payment_method' => 'qris', 'amount' => $nonCashSales, 'status' => 'success']);
        $order2->update(['payment_id' => $payment2->id]);

        return $shift;
    }

    public function test_get_rows_reports_sales_movements_and_reconciliation(): void
    {
        $user = User::factory()->create(['name' => 'Andi']);
        $this->actingAs($user);
        $shift = $this->makeShiftWithSales($user, 100000, 60000, 40000);

        CashMovement::create(['shift_id' => $shift->id, 'type' => CashMovementType::In, 'amount' => 10000, 'reason' => 'x', 'created_by' => $user->id]);
        CashMovement::create(['shift_id' => $shift->id, 'type' => CashMovementType::Out, 'amount' => 5000, 'reason' => 'y', 'created_by' => $user->id]);

        $shift->update(['expected_cash' => 165000, 'actual_cash' => 165000, 'difference' => 0, 'status' => ShiftStatus::Closed, 'closed_at' => now()]);

        $rows = Livewire::test(ShiftReport::class)->instance()->getRows();

        $this->assertCount(1, $rows);
        $row = $rows->first();

        $this->assertSame('Andi', $row['user']);
        $this->assertEquals(100000.0, $row['opening_cash']);
        $this->assertEquals(60000.0, $row['cash_sales']);
        $this->assertEquals(40000.0, $row['non_cash_sales']);
        $this->assertEquals(10000.0, $row['cash_in']);
        $this->assertEquals(5000.0, $row['cash_out']);
        $this->assertEquals(165000.0, $row['expected_cash']);
        $this->assertEquals(165000.0, $row['actual_cash']);
        $this->assertEquals(0.0, $row['difference']);
    }

    public function test_user_id_filter_narrows_rows_to_one_cashier(): void
    {
        $andi = User::factory()->create(['name' => 'Andi']);
        $budi = User::factory()->create(['name' => 'Budi']);
        $this->actingAs($andi);

        $this->makeShiftWithSales($andi, 100000, 10000, 0);
        $this->makeShiftWithSales($budi, 50000, 20000, 0);

        $rows = Livewire::test(ShiftReport::class)
            ->set('data.user_id', $andi->id)
            ->instance()
            ->getRows();

        $this->assertCount(1, $rows);
        $this->assertSame('Andi', $rows->first()['user']);
    }

    public function test_csv_export_contains_header_and_data(): void
    {
        $user = User::factory()->create(['name' => 'Andi']);
        $this->actingAs($user);
        $this->makeShiftWithSales($user, 100000, 60000, 0);

        $test = Livewire::test(ShiftReport::class)->callAction('exportCsv');

        $test->assertFileDownloaded(contentType: 'text/csv');
        $content = base64_decode(data_get($test->effects, 'download.content'));

        $this->assertStringContainsString('Kasir', $content);
        $this->assertStringContainsString('Andi', $content);
        $this->assertStringContainsString('60000', $content);
    }

    public function test_pdf_export_returns_a_pdf(): void
    {
        $user = User::factory()->create(['name' => 'Andi']);
        $this->actingAs($user);
        $this->makeShiftWithSales($user, 100000, 60000, 0);

        $test = Livewire::test(ShiftReport::class)->callAction('exportPdf');

        $test->assertFileDownloaded(contentType: 'application/pdf');
        $content = base64_decode(data_get($test->effects, 'download.content'));

        $this->assertStringStartsWith('%PDF', $content);
    }
}
