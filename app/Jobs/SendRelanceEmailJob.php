<?php

namespace App\Jobs;

use App\Mail\ClientRelanceMail;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendRelanceEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly Client $client,
        public readonly array  $invoices,    // array of Invoice models
        public readonly string $type,        // amiable | formelle | mise_en_demeure
        public readonly string $message,
        public readonly float  $totalDu,
    ) {}

    public function handle(): void
    {
        // Primary client email
        if ($this->client->email) {
            Mail::to($this->client->email, $this->client->name)
                ->send(new ClientRelanceMail($this->client, $this->invoices, $this->type, $this->message, $this->totalDu));
        }

        // Additional contacts who receive invoices
        $this->client->contacts()
            ->where('receives_invoices', true)
            ->whereNotNull('email')
            ->each(function ($contact) {
                Mail::to($contact->email, trim($contact->first_name . ' ' . $contact->last_name))
                    ->send(new ClientRelanceMail($this->client, $this->invoices, $this->type, $this->message, $this->totalDu));
            });

        Log::info("Relance email sent", [
            'client_id' => $this->client->id,
            'type'      => $this->type,
            'invoices'  => count($this->invoices),
            'total_du'  => $this->totalDu,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Failed to send relance email", [
            'client_id' => $this->client->id,
            'type'      => $this->type,
            'error'     => $exception->getMessage(),
        ]);
    }
}
