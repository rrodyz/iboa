<?php

namespace App\Jobs;

use App\Services\Integrations\IntegrationManager;
use App\Services\Integrations\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Send an SMS notification asynchronously.
 * Silent if no active SMS integration is configured.
 */
class SendSmsNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int   $tries   = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly string $phone,
        public readonly string $message,
        public readonly string $context = 'general',
    ) {}

    public function handle(): void
    {
        $integration = IntegrationManager::getActive('sms');

        if (! $integration) {
            Log::debug("[SendSmsNotification] No active SMS integration — SMS skipped.", [
                'phone' => $this->phone, 'context' => $this->context,
            ]);
            return;
        }

        $result = (new SmsService($integration))->send($this->phone, $this->message);

        if (! ($result['success'] ?? false)) {
            throw new \RuntimeException("SMS send failed: " . ($result['error'] ?? 'Unknown error'));
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::warning("[SendSmsNotification] All retries exhausted for {$this->phone}: " . $e->getMessage());
    }
}
