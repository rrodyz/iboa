<?php

namespace App\Jobs;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendInvoiceEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Maximum attempts before failing. */
    public int $tries = 3;

    /** Seconds to wait between retries. */
    public int $backoff = 60;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly string  $recipientEmail,
        public readonly string  $recipientName,
    ) {}

    public function handle(): void
    {
        $this->invoice->loadMissing(['client', 'items.product', 'createdBy']);

        Mail::to($this->recipientEmail, $this->recipientName)
            ->send(new InvoiceMail($this->invoice));

        // Mark as sent if still in emise status
        if ($this->invoice->status === 'emise') {
            $this->invoice->update([
                'status'  => 'envoyee',
                'sent_at' => now(),
            ]);
        }

        Log::info("Invoice email sent", [
            'invoice_id' => $this->invoice->id,
            'invoice_nb' => $this->invoice->number,
            'recipient'  => $this->recipientEmail,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Failed to send invoice email", [
            'invoice_id' => $this->invoice->id,
            'recipient'  => $this->recipientEmail,
            'error'      => $exception->getMessage(),
        ]);
    }
}
