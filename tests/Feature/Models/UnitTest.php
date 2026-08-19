<?php

namespace Tests\Feature\Models;

use App\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_unit(): void
    {
        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg']);

        $this->assertSame('Kilogram', $unit->fresh()->name);
        $this->assertSame('kg', $unit->fresh()->symbol);
    }

    public function test_enforces_unique_symbol(): void
    {
        Unit::create(['name' => 'Kilogram', 'symbol' => 'kg']);

        $this->expectException(QueryException::class);

        Unit::create(['name' => 'Kilo', 'symbol' => 'kg']);
    }
}
