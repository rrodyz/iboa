<?php

namespace App\Events;

use App\Models\CreditNote;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditNoteValidated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly CreditNote $creditNote) {}
}
