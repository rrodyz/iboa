<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceValidated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Invoice $invoice) {}
}
