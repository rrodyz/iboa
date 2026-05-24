<?php

namespace App\Observers;

use App\Observers\Concerns\TracksLifecycle;

class StockTransferObserver
{
    use TracksLifecycle;

    protected function summaryFields(): array
    {
        return ['number', 'status', 'from_warehouse_id', 'to_warehouse_id'];
    }
}
