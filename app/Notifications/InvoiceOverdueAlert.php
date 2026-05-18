<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class InvoiceOverdueAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'invoice_overdue',
            'icon'       => 'exclamation-circle',
            'color'      => 'red',
            'title'      => 'Facture en retard',
            'message'    => 'La facture '.$this->invoice->number.' de '
                           .($this->invoice->client?->name ?? '—')
                           .' est en retard de paiement ('
                           .number_format($this->invoice->remaining_amount, 0, ',', ' ').' FCFA).',
            'url'        => route('ventes.factures.show', $this->invoice),
            'model_type' => 'Invoice',
            'model_id'   => $this->invoice->id,
        ];
    }
}
