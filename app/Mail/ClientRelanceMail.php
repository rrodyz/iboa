<?php

namespace App\Mail;

use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ClientRelanceMail extends Mailable
{
    public string $typeLabel;
    public int    $totalDu;

    public function __construct(
        public Client     $client,
        public Collection $invoices,
        public string     $type,
        public ?string    $message = null,
    ) {
        $this->typeLabel = match($type) {
            'amiable'         => '1ère relance amiable',
            'formelle'        => '2ème relance formelle',
            'mise_en_demeure' => 'Mise en demeure',
            default           => 'Relance',
        };
        $this->totalDu = (int) $invoices->sum('remaining_amount');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[RELANCE] '.$this->typeLabel.' — '.$this->invoices->count().' facture(s) impayée(s)',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.relance',
        );
    }
}
