<?php

namespace App\Observers;

use App\Observers\Concerns\TracksLifecycle;

class RfqObserver
{
    use TracksLifecycle;

    protected function summaryFields(): array
    {
        return ['number', 'status', 'title'];
    }
}
