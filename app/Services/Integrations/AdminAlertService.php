<?php

namespace App\Services\Integrations;

use App\Models\ApiIntegration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Sends admin notifications when an integration encounters errors.
 *
 * - Throttled at call-site (BaseApiService) — max 1 alert/hour/integration.
 * - Uses Laravel Log channel as primary channel (zero config required).
 * - Optionally sends email if ADMIN_EMAIL is set in .env.
 * - Never throws — errors here must never interrupt the main flow.
 */
class AdminAlertService
{
    public static function notifyIntegrationError(ApiIntegration $integration, string $error): void
    {
        try {
            $subject = "[ERP] ⚠️ Intégration « {$integration->name} » en erreur";
            $body    = implode("\n", [
                "Intégration : {$integration->name} ({$integration->provider})",
                "Type        : {$integration->typeLabel()}",
                "Mode        : " . strtoupper($integration->mode),
                "Statut      : {$integration->status}",
                "Erreur      : {$error}",
                "Heure       : " . now()->format('d/m/Y H:i:s'),
                "Nb erreurs  : {$integration->error_count}",
                "",
                "Action requise : vérifier les credentials dans ERP > Intégrations",
                "URL directe  : " . route('integrations.show', $integration),
            ]);

            // 1. Always log
            Log::channel('stack')->error($subject, [
                'integration_id'   => $integration->id,
                'integration_name' => $integration->name,
                'provider'         => $integration->provider,
                'error'            => $error,
                'error_count'      => $integration->error_count,
            ]);

            // 2. Email if ADMIN_EMAIL configured
            $adminEmail = config('app.admin_email');
            if ($adminEmail && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                Mail::raw($body, function ($msg) use ($adminEmail, $subject) {
                    $msg->to($adminEmail)
                        ->subject($subject)
                        ->from(config('mail.from.address'), config('mail.from.name'));
                });
            }

        } catch (\Throwable $e) {
            // Silent — never let notifications crash the app
            Log::warning("AdminAlertService failed: " . $e->getMessage());
        }
    }

    /**
     * Notify when an integration recovers (goes from error → ok).
     */
    public static function notifyIntegrationRecovered(ApiIntegration $integration): void
    {
        try {
            Log::info("[ERP] ✅ Intégration « {$integration->name} » rétablie.", [
                'integration_id' => $integration->id,
                'provider'       => $integration->provider,
            ]);

            $adminEmail = config('app.admin_email');
            if ($adminEmail && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                Mail::raw(
                    "L'intégration « {$integration->name} » ({$integration->provider}) est à nouveau opérationnelle.",
                    fn($msg) => $msg
                        ->to($adminEmail)
                        ->subject("[ERP] ✅ Intégration rétablie : {$integration->name}")
                );
            }
        } catch (\Throwable) {}
    }
}
