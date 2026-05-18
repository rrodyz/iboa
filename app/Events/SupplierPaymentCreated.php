<?php

namespace App\Events;

use App\Models\SupplierPayment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupplierPaymentCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly SupplierPayment $payment) {}
}
