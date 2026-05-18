<?php

namespace App\Notifications;

use App\Models\ClientPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ClientPayment $payment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'payment_received',
            'icon'       => 'cash',
            'color'      => 'green',
            'title'      => 'Paiement reçu',
            'message'    => 'Encaissement de '
                           .number_format($this->payment->amount, 0, ',', ' ').' FCFA reçu de '
                           .($this->payment->client?->name ?? '—').'.',
            'url'        => route('tresorerie.encaissements.index'),
            'model_type' => 'ClientPayment',
            'model_id'   => $this->payment->id,
        ];
    }
}
