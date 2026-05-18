<?php

namespace App\Events;

use App\Models\SupplierInvoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupplierInvoiceValidated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly SupplierInvoice $invoice) {}
}
