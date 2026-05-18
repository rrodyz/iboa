<?php

namespace App\Events;

use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockAlertTriggered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ProductStock $productStock,
        public readonly float        $availableQuantity,
        public readonly float        $stockMin,
    ) {}
}
