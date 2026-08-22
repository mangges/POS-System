<?php

namespace Tests\Feature\Livewire\Pos;

use App\Enum\Shifts\ShiftStatus;
use App\Livewire\Pos\Cashier;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashierEndShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_expected_cash_is_shown_when_opening_the_end_shift_modal(): void
    {
        $user = User::factory()->create();
        Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 100000,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);
        $this->actingAs($user);

        Livewire::test(Cashier::class)
            ->call('openEndShiftModal')
            ->assertSet('showEndShiftModal', true)
            ->assertSet('endShiftExpectedCash', 100000.0);
    }

    public function test_closing_the_shift_resets_active_shift_and_persists_the_reconciliation(): void
    {
        $user = User::factory()->create();
        $shift = Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 100000,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);
        $this->actingAs($user);

        Livewire::test(Cashier::class)
            ->call('openEndShiftModal')
            ->set('endShiftActualCash', '99500')
            ->set('endShiftNote', 'Kurang Rp 500')
            ->call('endShift')
            ->assertSet('activeShift', null)
            ->assertSee('Buka Shift');

        $shift->refresh();
        $this->assertSame(ShiftStatus::Closed, $shift->status);
        $this->assertSame(100000.0, (float) $shift->expected_cash);
        $this->assertSame(99500.0, (float) $shift->actual_cash);
        $this->assertSame(-500.0, (float) $shift->difference);
        $this->assertSame('Kurang Rp 500', $shift->note);
    }

    public function test_actual_cash_is_required_to_close(): void
    {
        $user = User::factory()->create();
        Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 100000,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);
        $this->actingAs($user);

        Livewire::test(Cashier::class)
            ->call('openEndShiftModal')
            ->set('endShiftActualCash', '')
            ->call('endShift')
            ->assertHasErrors('endShiftActualCash')
            ->assertSet('showEndShiftModal', true);

        $this->assertSame(1, Shift::where('status', ShiftStatus::Open->value)->count());
    }
}
