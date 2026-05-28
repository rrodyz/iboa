<?php

namespace App\Services\Integrations;

use App\Models\ApiIntegration;
use App\Models\ApiLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Base class for all external API integrations.
 *
 * Contract:
 *  - Uses the effective base URL (sandbox or production) from the model.
 *  - Logs every call to api_logs.
 *  - Marks the integration OK/Error automatically.
 *  - Does NOT retry on 4xx (client errors — not transient).
 *  - Retries 2× on 5xx / network errors with exponential backoff.
 *  - Configurable per-integration timeout (default 30s).
 */
abstract class BaseApiService
{
    protected ApiIntegration $integration;

    public function __construct(ApiIntegration $integration)
    {
        $this->integration = $integration;
    }

    // ── HTTP client ───────────────────────────────────────────────────────────

    /**
     * Build a pre-configured HTTP client for this integration.
     * @param array $headers  Extra headers merged with defaults.
     * @param bool  $retry    Whether to retry on 5xx. Disable for idempotency-sensitive calls.
     */
    protected function http(array $headers = [], bool $retry = true): PendingRequest
    {
        $timeout = max(5, (int) ($this->integration->timeout_seconds ?? 30));
        $baseUrl = $this->integration->effectiveBaseUrl();

        $client = Http::baseUrl($baseUrl)
            ->timeout($timeout)
            ->withHeaders(array_merge([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ], $headers));

        if ($retry) {
            // Retry only on connection errors and 5xx — NOT on 4xx (auth failures, etc.)
            $client = $client->retry(2, 1000, function (\Throwable $e, $response) {
                if ($response && $response->status() < 500) {
                    return false; // Don't retry 4xx
                }
                return true;
            }, throw: false);
        }

        return $client;
    }

    // ── Core call method ──────────────────────────────────────────────────────

    /**
     * Execute a logged HTTP call.
     *
     * Returns array:
     *   ['success' => bool, 'status' => int|null, 'data' => array|null, 'error' => string|null]
     */
    protected function call(
        string $method,
        string $endpoint,
        array  $payload  = [],
        array  $headers  = [],
        bool   $retry    = true,
        bool   $logSensitive = false,
    ): array {
        $startTime  = microtime(true);
        $success    = false;
        $statusCode = null;
        $response   = null;
        $error      = null;

        try {
            $http = $this->http($headers, $retry);

            $res = match (strtoupper($method)) {
                'GET'    => $http->get($endpoint, $payload),
                'PUT'    => $http->put($endpoint, $payload),
                'PATCH'  => $http->patch($endpoint, $payload),
                'DELETE' => $http->delete($endpoint, $payload),
                default  => $http->post($endpoint, $payload),
            };

            $statusCode = $res->status();
            $success    = $res->successful();
            $response   = $res->json() ?? ['raw' => substr($res->body(), 0, 1000)];

            if ($success) {
                $this->integration->markOk();
            } else {
                $error = $this->extractError($res->body(), $statusCode);
                $this->integration->markError($error);
                $this->maybeNotifyAdmin($error);
            }

        } catch (\Throwable $e) {
            $error = $this->classifyNetworkError($e);
            $this->integration->markError($error);
            $this->maybeNotifyAdmin($error);
            Log::error("[Integration:{$this->integration->slug}] {$error}", [
                'endpoint' => $endpoint,
                'method'   => $method,
            ]);
        } finally {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            // Scrub credentials from logged payload
            $logPayload = $logSensitive ? $payload : $this->scrubSensitive($payload);
            $this->log($method, $endpoint, $logPayload, $response, $statusCode, $success, $duration, $error);
        }

        return [
            'success' => $success,
            'status'  => $statusCode,
            'data'    => $response,
            'error'   => $error,
        ];
    }

    // ── OAuth2 helper ─────────────────────────────────────────────────────────

    /**
     * Perform an OAuth2 client_credentials token request.
     * Uses form encoding (standard OAuth2).
     * Does NOT log (credentials would appear in payload).
     */
    protected function fetchOAuthToken(
        string $tokenEndpoint,
        string $clientId,
        string $clientSecret,
        array  $extraFields = [],
    ): ?string {
        try {
            $baseUrl = $this->integration->effectiveBaseUrl();
            $res = Http::baseUrl($baseUrl)
                ->timeout(15)
                ->withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->post($tokenEndpoint, array_merge(
                    ['grant_type' => 'client_credentials'],
                    $extraFields
                ));

            if ($res->successful()) {
                return $res->json('access_token');
            }

            $this->integration->markError("OAuth token error ({$res->status()}): " . substr($res->body(), 0, 200));
        } catch (\Throwable $e) {
            $this->integration->markError("OAuth exception: " . $e->getMessage());
        }

        return null;
    }

    // ── Ping ─────────────────────────────────────────────────────────────────

    /**
     * Simple connectivity test — override in subclasses for a real health check.
     */
    public function ping(): array
    {
        return ['success' => false, 'message' => 'No ping method implemented for this provider.'];
    }

    // ── Logging ───────────────────────────────────────────────────────────────

    protected function log(
        string  $method,
        string  $endpoint,
        array   $payload,
        mixed   $response,
        ?int    $statusCode,
        bool    $success,
        float   $duration,
        ?string $error = null,
        string  $direction = 'outbound',
    ): void {
        try {
            ApiLog::create([
                'api_integration_id' => $this->integration->id,
                'service'            => $this->integration->provider,
                'endpoint'           => $endpoint,
                'method'             => strtoupper($method),
                'payload'            => $payload,
                'response'           => is_array($response) ? $response : ['raw' => $response],
                'status_code'        => $statusCode,
                'success'            => $success,
                'duration_ms'        => $duration,
                'direction'          => $direction,
                'error_message'      => $error ? substr($error, 0, 500) : null,
                'created_at'         => now(),
            ]);
        } catch (\Throwable) {
            // Never let logging break the main flow
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function extractError(string $body, ?int $status): string
    {
        $json = json_decode($body, true);
        if (is_array($json)) {
            return $json['message'] ?? $json['error'] ?? $json['error_description']
                ?? "HTTP {$status}: " . substr($body, 0, 200);
        }
        return "HTTP {$status}: " . substr($body, 0, 200);
    }

    private function classifyNetworkError(\Throwable $e): string
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'timed out') || str_contains($msg, 'cURL error 28')) {
            return "Timeout — le fournisseur n'a pas répondu dans les délais ({$this->integration->timeout_seconds}s).";
        }
        if (str_contains($msg, 'Could not resolve') || str_contains($msg, 'cURL error 6')) {
            return "Résolution DNS échouée — URL inaccessible ou erronée.";
        }
        if (str_contains($msg, 'Connection refused') || str_contains($msg, 'cURL error 7')) {
            return "Connexion refusée — le serveur API est peut-être hors ligne.";
        }
        return $e->getMessage();
    }

    private function scrubSensitive(array $data): array
    {
        $sensitiveKeys = ['api_key', 'secret', 'password', 'token', 'authorization', 'client_secret'];
        array_walk_recursive($data, function (&$value, $key) use ($sensitiveKeys) {
            foreach ($sensitiveKeys as $sk) {
                if (stripos((string) $key, $sk) !== false) {
                    $value = '***';
                    break;
                }
            }
        });
        return $data;
    }

    private function maybeNotifyAdmin(string $error): void
    {
        if (! $this->integration->notify_on_error) return;

        // Throttle: notify at most once per hour per integration
        $cacheKey = "integration_admin_alert_{$this->integration->id}";
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) return;

        \Illuminate\Support\Facades\Cache::put($cacheKey, 1, now()->addHour());

        try {
            \App\Services\Integrations\AdminAlertService::notifyIntegrationError(
                $this->integration,
                $error
            );
        } catch (\Throwable) {}
    }
}
