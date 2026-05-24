<?php

namespace App\Observers;

use App\Observers\Concerns\TracksLifecycle;

class QuoteObserver
{
    use TracksLifecycle;

    protected function summaryFields(): array
    {
        return ['number', 'status', 'client_id', 'reference'];
    }
}
