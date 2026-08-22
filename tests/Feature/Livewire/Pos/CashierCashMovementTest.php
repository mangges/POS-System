<?php

namespace Tests\Feature\Livewire\Pos;

use App\Enum\Shifts\CashMovementType;
use App\Enum\Shifts\ShiftStatus;
use App\Livewire\Pos\Cashier;
use App\Models\CashMovement;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashierCashMovementTest extends TestCase
{
    use RefreshDatabase;

    private function openShiftFor(User $user): Shift
    {
        return Shift::create([
            'user_id' => $user->id,
            'opening_cash' => 100000,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);
    }

    public function test_recording_a_cash_in_movement(): void
    {
        $user = User::factory()->create();
        $shift = $this->openShiftFor($user);
        $this->actingAs($user);

        Livewire::test(Cashier::class)
            ->call('openCashMovementModal')
            ->assertSet('showCashMovementModal', true)
            ->set('cashMovementType', 'in')
            ->set('cashMovementAmount', '25000')
            ->set('cashMovementReason', 'Tambah modal')
            ->call('recordCashMovement')
            ->assertSet('showCashMovementModal', false);

        $movement = CashMovement::first();
        $this->assertSame($shift->id, $movement->shift_id);
        $this->assertSame(CashMovementType::In, $movement->type);
        $this->assertSame(25000.0, (float) $movement->amount);
        $this->assertSame('Tambah modal', $movement->reason);
        $this->assertSame($user->id, $movement->created_by);
    }

    public function test_recording_a_cash_out_movement(): void
    {
        $user = User::factory()->create();
        $this->openShiftFor($user);
        $this->actingAs($user);

        Livewire::test(Cashier::class)
            ->set('cashMovementType', 'out')
            ->set('cashMovementAmount', '15000')
            ->set('cashMovementReason', 'Setor ke bank')
            ->call('recordCashMovement');

        $this->assertSame(CashMovementType::Out, CashMovement::first()->type);
    }

    public function test_recording_a_cash_movement_on_an_already_closed_shift_adds_a_form_error(): void
    {
        $user = User::factory()->create();
        $shift = $this->openShiftFor($user);
        $shift->update(['status' => ShiftStatus::Closed, 'closed_at' => now()]);
        $this->actingAs($user);

        Livewire::test(Cashier::class)
            ->set('activeShift', $shift)
            ->set('cashMovementType', 'in')
            ->set('cashMovementAmount', '25000')
            ->set('cashMovementReason', 'Tambah modal')
            ->call('recordCashMovement')
            ->assertHasErrors('cashMovementAmount');

        $this->assertSame(0, CashMovement::count());
    }

    public function test_amount_and_reason_are_required(): void
    {
        $user = User::factory()->create();
        $this->openShiftFor($user);
        $this->actingAs($user);

        Livewire::test(Cashier::class)
            ->set('cashMovementType', 'in')
            ->set('cashMovementAmount', '0')
            ->set('cashMovementReason', '')
            ->call('recordCashMovement')
            ->assertHasErrors(['cashMovementAmount', 'cashMovementReason']);

        $this->assertSame(0, CashMovement::count());
    }
}
