<?php

namespace App\Observers;

use App\Observers\Concerns\TracksLifecycle;

class PurchaseOrderObserver
{
    use TracksLifecycle;

    protected function summaryFields(): array
    {
        return ['number', 'status', 'approval_status', 'supplier_id'];
    }
}
