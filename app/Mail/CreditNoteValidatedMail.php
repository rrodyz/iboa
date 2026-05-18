<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\CreditNote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CreditNoteValidatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CreditNote $creditNote) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Avoir ' . $this->creditNote->number . ' — ' . ($this->creditNote->company?->trade_name ?? $this->creditNote->company?->name ?? 'A3 ERP'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.credit-note-validated',
        );
    }

    public function attachments(): array
    {
        $creditNote = $this->creditNote;
        $settings   = Company::first()?->documentSetting;
        $pdf        = Pdf::loadView('ventes.pdf.credit-note', compact('creditNote', 'settings'))
            ->setPaper(strtolower($settings?->page_size ?? 'a4'), $settings?->orientation ?? 'portrait');
        $filename = 'Avoir_' . str_replace(['/', '\\', ' '], '-', $creditNote->number) . '.pdf';

        return [
            Attachment::fromData(fn () => $pdf->output(), $filename)
                ->withMime('application/pdf'),
        ];
    }
}
