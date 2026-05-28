<?php

namespace App\Jobs;

use App\Models\ExternalTransaction;
use App\Services\Integrations\PaymentConfirmationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Process an external payment confirmation.
 *
 * - ShouldBeUnique prevents duplicate processing of the same transaction
 *   (e.g. two webhook deliveries for the same event).
 * - Progressive backoff: 1 min, 5 min, 15 min between retries.
 * - On final failure: marks the transaction for manual review (not as 'failed').
 */
class ProcessExternalPayment implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int   $tries           = 3;
    public int   $uniqueFor       = 3600;       // Lock held for 1 hour max
    public array $backoff         = [60, 300, 900]; // 1min, 5min, 15min

    public function __construct(public readonly ExternalTransaction $transaction) {}

    /**
     * Unique key: one job per transaction.
     */
    public function uniqueId(): string
    {
        return 'ext_payment_' . $this->transaction->id;
    }

    public function handle(PaymentConfirmationService $service): void
    {
        Log::info("[ProcessExternalPayment] Processing {$this->transaction->internal_reference}", [
            'attempt'  => $this->attempts(),
            'provider' => $this->transaction->provider,
        ]);

        // Update retry counter
        $this->transaction->increment('retry_count');
        $this->transaction->update(['last_retry_at' => now()]);

        $service->confirm($this->transaction);
    }

    /**
     * Called after all retries are exhausted.
     * We DON'T mark as 'failed' (the money was received) — we flag for manual review.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[ProcessExternalPayment] ❌ Exhausted retries for {$this->transaction->internal_reference}", [
            'error'    => $exception->getMessage(),
            'provider' => $this->transaction->provider,
            'amount'   => $this->transaction->amount,
        ]);

        // Flag for manual review — do NOT set status = 'failed'
        // The external payment was received; only our processing failed.
        $this->transaction->update([
            'failure_reason' => "Traitement échoué après {$this->tries} tentatives: "
                . substr($exception->getMessage(), 0, 200),
        ]);

        // Notify admin
        try {
            \App\Services\Integrations\AdminAlertService::notifyIntegrationError(
                $this->transaction->integration,
                "Job ProcessExternalPayment échoué pour {$this->transaction->internal_reference}: "
                . $exception->getMessage()
            );
        } catch (\Throwable) {}
    }
}
