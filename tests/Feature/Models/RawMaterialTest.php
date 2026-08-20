<?php

namespace Tests\Feature\Models;

use App\Models\RawMaterial;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawMaterialTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_a_unit(): void
    {
        $unit = Unit::create(['name' => 'Gram', 'symbol' => 'g']);
        $material = RawMaterial::create(['name' => 'Sugar', 'unit_id' => $unit->id, 'stock' => 10]);

        $this->assertTrue($material->unit->is($unit));
        $this->assertSame('g', $material->unit->symbol);
    }
}
