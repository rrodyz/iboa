<?php
namespace App\Services\Integrations\Contracts;

interface SmsGatewayInterface
{
    /**
     * Send a single SMS.
     */
    public function send(string $to, string $message): array;

    /**
     * Send SMS to multiple recipients.
     */
    public function sendBulk(array $recipients, string $message): array;
}
