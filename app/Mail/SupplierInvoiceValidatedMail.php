<?php

namespace App\Mail;

use App\Models\SupplierInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupplierInvoiceValidatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SupplierInvoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Comptabilité] Facture fournisseur validée — ' . $this->invoice->number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.supplier-invoice-validated',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
