<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    protected $signature   = 'invoices:mark-overdue';
    protected $description = 'Marque les factures échues non payées avec le statut en_retard';

    public function handle(): int
    {
        $count = Invoice::markOverdue();
        $this->info("$count facture(s) marquée(s) en retard.");
        return self::SUCCESS;
    }
}
