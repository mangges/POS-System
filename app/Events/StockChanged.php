<?php

namespace App\Events;

use App\Models\Product;
use App\Models\RawMaterial;
use Illuminate\Foundation\Events\Dispatchable;

class StockChanged
{
    use Dispatchable;

    public function __construct(public Product|RawMaterial $stockable) {}
}