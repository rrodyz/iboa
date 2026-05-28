<?php
namespace App\Services\Integrations\Contracts;

use App\Models\ApiIntegration;
use App\Models\ExternalTransaction;

interface PaymentGatewayInterface
{
    /**
     * Initiate a payment request (push to customer phone).
     */
    public function initiatePayment(array $params): array;

    /**
     * Check the status of a transaction.
     */
    public function checkStatus(string $externalReference): array;

    /**
     * Process an incoming webhook payload and return normalized data.
     */
    public function processWebhook(array $payload, string $signature = ''): array;

    /**
     * Verify that a webhook signature is valid.
     */
    public function verifyWebhookSignature(array $payload, string $signature): bool;
}
