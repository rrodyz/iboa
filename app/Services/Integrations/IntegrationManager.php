<?php

namespace App\Services\Integrations;

use App\Models\ApiIntegration;
use App\Services\Integrations\Contracts\PaymentGatewayInterface;
use App\Services\Integrations\Contracts\SmsGatewayInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Factory + registry for all integration services.
 *
 * Usage:
 *   $service = IntegrationManager::make($integration);
 *   $active  = IntegrationManager::getActive('payment');
 */
class IntegrationManager
{
    /**
     * Provider → service class map.
     * Extend this to add new providers without modifying existing code.
     */
    private const PROVIDER_MAP = [
        'orange_money'  => OrangeMoneyService::class,
        'moov_money'    => MoovMoneyService::class,
        'nexah'         => SmsService::class,
        'twilio'        => SmsService::class,
        'sms_generic'   => SmsService::class,
        'fiscal_bf'     => FiscalBfService::class,
        // email, bank, ecommerce: add concrete classes here as they are built
    ];

    /**
     * Instantiate the correct service for a given integration.
     * Returns null if the provider is not (yet) mapped.
     */
    public static function make(ApiIntegration $integration): ?BaseApiService
    {
        $class = self::PROVIDER_MAP[$integration->provider] ?? null;
        if (! $class) return null;

        return new $class($integration);
    }

    /**
     * Get the first active integration of a given type, with 60-second cache.
     */
    public static function getActive(string $type): ?ApiIntegration
    {
        return Cache::remember(
            "integration_{$type}_active",
            60,
            fn () => ApiIntegration::where('type', $type)->where('is_active', true)->first()
        );
    }

    /**
     * Get a specific provider's active integration.
     */
    public static function getActiveByProvider(string $provider): ?ApiIntegration
    {
        return Cache::remember(
            "integration_provider_{$provider}_active",
            60,
            fn () => ApiIntegration::where('provider', $provider)->where('is_active', true)->first()
        );
    }

    /**
     * Resolve a service for a given type (returns null if no active integration).
     */
    public static function resolveType(string $type): ?BaseApiService
    {
        $integration = self::getActive($type);
        if (! $integration) return null;
        return self::make($integration);
    }

    /**
     * Invalidate cache for a given type.
     * Call after create / update / delete / toggle.
     */
    public static function forget(string $type): void
    {
        Cache::forget("integration_{$type}_active");
    }

    /**
     * Invalidate all integration caches.
     */
    public static function forgetAll(): void
    {
        foreach (['payment', 'sms', 'email', 'bank', 'fiscal', 'ecommerce'] as $type) {
            self::forget($type);
        }
        // Also forget token caches
        Cache::flush(); // Only if safe in this environment
    }

    /**
     * List all registered provider slugs.
     */
    public static function registeredProviders(): array
    {
        return array_keys(self::PROVIDER_MAP);
    }

    /**
     * Check whether a provider has a service class registered.
     */
    public static function hasProvider(string $provider): bool
    {
        return isset(self::PROVIDER_MAP[$provider]);
    }
}
