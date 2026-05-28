<?php
namespace App\Services\Integrations;

use App\Models\ApiIntegration;
use App\Services\Integrations\Contracts\SmsGatewayInterface;
use Illuminate\Support\Facades\Log;

/**
 * Generic SMS Gateway service.
 * Supports: Nexah, Twilio, Orange SMS API, others via config.
 */
class SmsService extends BaseApiService implements SmsGatewayInterface
{
    public function send(string $to, string $message): array
    {
        $provider = $this->integration->provider;

        return match($provider) {
            'nexah'  => $this->sendViaNexah($to, $message),
            'twilio' => $this->sendViaTwilio($to, $message),
            default  => $this->sendViaGenericRest($to, $message),
        };
    }

    public function sendBulk(array $recipients, string $message): array
    {
        $results = [];
        foreach ($recipients as $to) {
            $results[$to] = $this->send($to, $message);
        }
        return $results;
    }

    private function sendViaNexah(string $to, string $message): array
    {
        return $this->call('POST', '/sms/send', [
            'apiKey'   => $this->integration->api_key,
            'to'       => $to,
            'from'     => $this->integration->extra_config['sender_id'] ?? 'A3ERP',
            'message'  => $message,
        ]);
    }

    private function sendViaTwilio(string $to, string $message): array
    {
        $sid    = $this->integration->client_id;
        $token  = $this->integration->token;
        $from   = $this->integration->extra_config['from_number'] ?? '';

        try {
            $res = \Illuminate\Support\Facades\Http::withBasicAuth($sid, $token)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => $from,
                    'To'   => $to,
                    'Body' => $message,
                ]);

            $this->log('POST', 'twilio/messages', compact('to', 'message'), $res->json(), $res->status(), $res->successful(), 0);
            return ['success' => $res->successful(), 'data' => $res->json()];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function sendViaGenericRest(string $to, string $message): array
    {
        return $this->call('POST', '/send', [
            'to'      => $to,
            'message' => $message,
            'api_key' => $this->integration->api_key,
        ]);
    }

    /**
     * Send invoice SMS notification.
     */
    public static function sendInvoiceNotification(string $phone, string $invoiceNumber, float $amount, string $dueDate): bool
    {
        $integration = ApiIntegration::where('type', 'sms')->where('is_active', true)->first();
        if (! $integration) return false;

        $message = "Bonjour, votre facture N° {$invoiceNumber} d'un montant de " . number_format($amount, 0, ',', ' ') . " FCFA est disponible. Échéance : {$dueDate}. Merci.";

        try {
            $svc = new self($integration);
            $result = $svc->send($phone, $message);
            return $result['success'] ?? false;
        } catch (\Throwable $e) {
            Log::warning("SMS send failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send payment confirmation SMS.
     */
    public static function sendPaymentConfirmation(string $phone, string $invoiceNumber, float $amount): bool
    {
        $integration = ApiIntegration::where('type', 'sms')->where('is_active', true)->first();
        if (! $integration) return false;

        $message = "Paiement reçu de " . number_format($amount, 0, ',', ' ') . " FCFA pour la facture {$invoiceNumber}. Merci pour votre confiance.";

        try {
            $svc = new self($integration);
            $result = $svc->send($phone, $message);
            return $result['success'] ?? false;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
