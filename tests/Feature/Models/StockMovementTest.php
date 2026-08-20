<?php

namespace Tests\Feature\Models;

use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_resolves_to_raw_material_via_morph_map(): void
    {
        $unit = Unit::create(['name' => 'Gram', 'symbol' => 'g']);
        $material = RawMaterial::create(['name' => 'Sugar', 'unit_id' => $unit->id, 'stock' => 10]);

        $movement = StockMovement::create([
            'reference_id' => $material->id,
            'reference_type' => 'raw_material',
            'type' => 'in',
            'quantity' => 5,
        ]);

        $this->assertTrue($movement->reference->is($material));
        $this->assertTrue($material->stockMovements->contains($movement));
    }

    public function test_creating_an_in_movement_increments_reference_stock(): void
    {
        $unit = Unit::create(['name' => 'Gram', 'symbol' => 'g']);
        $material = RawMaterial::create(['name' => 'Sugar', 'unit_id' => $unit->id, 'stock' => 10]);

        StockMovement::create([
            'reference_id' => $material->id,
            'reference_type' => 'raw_material',
            'type' => 'in',
            'quantity' => 5,
        ]);

        $this->assertEquals('15.00', $material->fresh()->stock);
    }

    public function test_creating_an_out_movement_decrements_reference_stock(): void
    {
        $unit = Unit::create(['name' => 'Gram', 'symbol' => 'g']);
        $material = RawMaterial::create(['name' => 'Sugar', 'unit_id' => $unit->id, 'stock' => 10]);

        StockMovement::create([
            'reference_id' => $material->id,
            'reference_type' => 'raw_material',
            'type' => 'out',
            'quantity' => 3,
        ]);

        $this->assertEquals('7.00', $material->fresh()->stock);
    }
}
