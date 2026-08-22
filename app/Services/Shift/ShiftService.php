<?php

namespace App\Services\Shift;

use App\Enum\Orders\PaymentStatus;
use App\Enum\Payments\PaymentMethod;
use App\Enum\Shifts\CashMovementType;
use App\Enum\Shifts\ShiftStatus;
use App\Models\Payment;
use App\Models\Shift;

class ShiftService
{
    public function open(int $userId, float $openingCash): Shift
    {
        $hasOpenShift = Shift::where('user_id', $userId)
            ->where('status', ShiftStatus::Open)
            ->exists();

        if ($hasOpenShift) {
            throw new \Exception('Anda masih punya shift yang belum ditutup.');
        }

        return Shift::create([
            'user_id' => $userId,
            'opening_cash' => $openingCash,
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
        ]);
    }

    public function previewExpectedCash(Shift $shift): float
    {
        $cashSales = Payment::whereHas('order', fn ($q) => $q->where('shift_id', $shift->id))
            ->where('payment_method', PaymentMethod::Cash->value)
            ->where('status', PaymentStatus::Success->value)
            ->sum('amount');

        $cashIn = $shift->cashMovements()->where('type', CashMovementType::In->value)->sum('amount');
        $cashOut = $shift->cashMovements()->where('type', CashMovementType::Out->value)->sum('amount');

        return (float) $shift->opening_cash + (float) $cashSales + (float) $cashIn - (float) $cashOut;
    }

    public function close(Shift $shift, float $actualCash, ?string $note = null): Shift
    {
        $expectedCash = $this->previewExpectedCash($shift);
        $difference = $actualCash - $expectedCash;

        $shift->update([
            'expected_cash' => $expectedCash,
            'actual_cash' => $actualCash,
            'difference' => $difference,
            'note' => $note,
            'status' => ShiftStatus::Closed,
            'closed_at' => now(),
        ]);

        return $shift->fresh();
    }
}
