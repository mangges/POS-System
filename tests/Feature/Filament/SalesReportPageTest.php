<?php

namespace Tests\Feature\Filament;

use App\Enum\Orders\OrderStatus;
use App\Filament\Pages\Reports\SalesReport;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesReportPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $status, ?string $paymentMethod = null, float $amount = 0, float $tax = 0): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'total_amount' => $amount,
            'tax' => $tax,
            'discount' => 0,
            'status' => $status,
            'order_type' => 'dine_in',
        ]);

        if ($paymentMethod) {
            $payment = Payment::create([
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'status' => 'success',
            ]);

            $order->update(['payment_id' => $payment->id]);
        }

        return $order->fresh();
    }

    public function test_only_completed_orders_are_counted_and_grouped_by_payment_method(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->makeOrder(OrderStatus::Completed->value, 'cash', 55000, 5000);
        $this->makeOrder(OrderStatus::Completed->value, 'qris', 30000, 3000);
        $this->makeOrder(OrderStatus::Pending->value, 'cash', 99999);
        $this->makeOrder(OrderStatus::Cancelled->value, 'cash', 99999);

        $rows = Livewire::test(SalesReport::class)->instance()->getRows();

        $this->assertCount(2, $rows);

        $cashRow = $rows->firstWhere('payment_method', 'Cash');
        $qrisRow = $rows->firstWhere('payment_method', 'QRIS');

        $this->assertNotNull($cashRow);
        $this->assertSame(1, $cashRow['order_count']);
        $this->assertEquals(50000.0, $cashRow['subtotal']);
        $this->assertEquals(5000.0, $cashRow['tax']);
        $this->assertEquals(55000.0, $cashRow['total']);

        $this->assertNotNull($qrisRow);
        $this->assertSame(1, $qrisRow['order_count']);
        $this->assertEquals(27000.0, $qrisRow['subtotal']);
        $this->assertEquals(3000.0, $qrisRow['tax']);
        $this->assertEquals(30000.0, $qrisRow['total']);
    }

    public function test_monthly_grouping_produces_one_row_per_calendar_month(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $thisMonthOrder = $this->makeOrder(OrderStatus::Completed->value, 'cash', 50000);
        $thisMonthOrder->forceFill(['created_at' => Carbon::now()])->save();

        $lastMonthOrder = $this->makeOrder(OrderStatus::Completed->value, 'qris', 30000);
        $lastMonthOrder->forceFill(['created_at' => Carbon::now()->subMonthNoOverflow()])->save();

        $rangeStart = Carbon::now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d');
        $rangeEnd = Carbon::now()->format('Y-m-d');

        $rows = Livewire::test(SalesReport::class)
            ->set('data.period_type', 'monthly')
            ->set('data.date_range', "{$rangeStart} - {$rangeEnd}")
            ->instance()
            ->getRows();

        $this->assertCount(2, $rows);

        $thisMonthKey = Carbon::now()->format('Y-m');
        $lastMonthKey = Carbon::now()->subMonthNoOverflow()->format('Y-m');

        $thisMonthRow = $rows->firstWhere('period', $thisMonthKey);
        $lastMonthRow = $rows->firstWhere('period', $lastMonthKey);

        $this->assertNotNull($thisMonthRow);
        $this->assertNotNull($lastMonthRow);
        $this->assertSame('Cash', $thisMonthRow['payment_method']);
        $this->assertSame('QRIS', $lastMonthRow['payment_method']);
        $this->assertEquals(50000.0, $thisMonthRow['total']);
        $this->assertEquals(30000.0, $lastMonthRow['total']);
    }

    public function test_csv_export_contains_header_row_and_data(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->makeOrder(OrderStatus::Completed->value, 'cash', 50000);

        $test = Livewire::test(SalesReport::class)->callAction('exportCsv');

        $test->assertFileDownloaded(contentType: 'text/csv');

        $content = base64_decode(data_get($test->effects, 'download.content'));

        // RFC 4180 CSV may quote fields with spaces; validate header columns exist
        $this->assertStringContainsString('Period', $content);
        $this->assertStringContainsString('Payment Method', $content);
        $this->assertStringContainsString('Orders', $content);
        $this->assertStringContainsString('Subtotal', $content);
        $this->assertStringContainsString('Tax', $content);
        $this->assertStringContainsString('Total', $content);
        $this->assertStringContainsString('Cash', $content);
        $this->assertStringContainsString('50000', $content);
    }

    public function test_pdf_export_returns_a_pdf(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->makeOrder(OrderStatus::Completed->value, 'cash', 50000);

        $test = Livewire::test(SalesReport::class)->callAction('exportPdf');

        $test->assertFileDownloaded(contentType: 'application/pdf');

        $content = base64_decode(data_get($test->effects, 'download.content'));

        $this->assertStringStartsWith('%PDF', $content);
    }
}
