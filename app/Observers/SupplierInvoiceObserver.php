<?php

namespace App\Observers;

use App\Observers\Concerns\TracksLifecycle;

class SupplierInvoiceObserver
{
    use TracksLifecycle;

    protected function summaryFields(): array
    {
        return ['number', 'supplier_invoice_number', 'status', 'supplier_id'];
    }
}
