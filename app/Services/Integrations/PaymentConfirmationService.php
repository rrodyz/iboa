<?php

namespace App\Services\Integrations;

use App\Models\ExternalTransaction;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Idempotent payment confirmation pipeline.
 *
 * Steps:
 *  1. Guard against double-processing (idempotent).
 *  2. DB transaction: mark confirmed, update invoice, create ClientPayment.
 *  3. Post-commit: send SMS (non-fatal if fails).
 *
 * Failure policy:
 *  - DB/invoice errors → rollback, set transaction status = 'processing_failed' (keeps amount)
 *  - SMS failure      → logged only (never fails the payment)
 */
class PaymentConfirmationService
{
    public function confirm(ExternalTransaction $transaction): bool
    {
        // ── Idempotency guard ─────────────────────────────────────────────────
        $transaction->refresh(); // Re-read from DB (the job may have a stale model)

        if ($transaction->status === 'confirmed') {
            Log::info("[PaymentConfirmation] Already confirmed: {$transaction->internal_reference}");
            return true;
        }

        if (in_array($transaction->status, ['cancelled'])) {
            Log::warning("[PaymentConfirmation] Transaction is {$transaction->status}, cannot confirm: {$transaction->internal_reference}");
            return false;
        }

        // ── DB transaction ────────────────────────────────────────────────────
        DB::beginTransaction();
        try {
            // 1. Mark as confirmed
            $transaction->update([
                'status'        => 'confirmed',
                'transacted_at' => $transaction->transacted_at ?? now(),
            ]);

            // 2. Update linked invoice + create encaissement
            if ($transaction->invoice_id) {
                $this->processInvoicePayment($transaction);
            }

            DB::commit();

            Log::info("[PaymentConfirmation] ✅ Confirmed {$transaction->internal_reference}", [
                'provider' => $transaction->provider,
                'amount'   => $transaction->amount,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            // Mark as processing_failed — the external payment WAS received,
            // but we failed to update our books. Keep status as pending for manual review.
            $transaction->update([
                'failure_reason' => "Erreur traitement: " . substr($e->getMessage(), 0, 250),
                // Keep status as 'pending' — operator can retry manually
            ]);

            Log::error("[PaymentConfirmation] ❌ Failed {$transaction->internal_reference}: " . $e->getMessage(), [
                'trace' => substr($e->getTraceAsString(), 0, 1000),
            ]);

            throw $e; // Re-throw so the job knows to retry
        }

        // ── Post-commit: SMS (non-fatal) ──────────────────────────────────────
        $this->sendConfirmationSms($transaction);

        return true;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function processInvoicePayment(ExternalTransaction $transaction): void
    {
        $invoice = Invoice::lockForUpdate()->find($transaction->invoice_id);
        if (! $invoice) return;

        $amount = (float) $transaction->amount;

        // Create encaissement (ClientPayment) if the model exists
        if (class_exists(\App\Models\ClientPayment::class)) {
            $payment = \App\Models\ClientPayment::create([
                'invoice_id'     => $invoice->id,
                'client_id'      => $invoice->client_id,
                'amount'         => $amount,
                'payment_date'   => now(),
                'payment_method' => $this->paymentMethodFromProvider($transaction->provider),
                'reference'      => $transaction->internal_reference,
                'notes'          => sprintf(
                    'Paiement %s — Réf ext: %s',
                    strtoupper($transaction->provider),
                    $transaction->external_reference ?? 'N/A'
                ),
            ]);

            $transaction->update(['client_payment_id' => $payment->id]);
        }

        // Update invoice amounts
        $totalPaid       = (float) ($invoice->paid_amount ?? 0) + $amount;
        $remainingAmount = max(0, (float) $invoice->total_ttc - $totalPaid);
        $newStatus       = $remainingAmount <= 0 ? 'payee' : 'partiellement_payee';

        $invoice->update([
            'paid_amount'      => $totalPaid,
            'remaining_amount' => $remainingAmount,
            'status'           => $newStatus,
        ]);

        Log::info("[PaymentConfirmation] Invoice #{$invoice->number} → {$newStatus} (paid: {$totalPaid})");
    }

    private function sendConfirmationSms(ExternalTransaction $transaction): void
    {
        try {
            $phone = $transaction->phone_number
                ?? $transaction->client?->phone
                ?? null;

            if (! $phone) return;

            $invoiceRef = $transaction->invoice?->number ?? $transaction->internal_reference;
            SmsService::sendPaymentConfirmation($phone, $invoiceRef, (float) $transaction->amount);
        } catch (\Throwable $e) {
            Log::warning("[PaymentConfirmation] SMS failed (non-fatal): " . $e->getMessage());
        }
    }

    private function paymentMethodFromProvider(string $provider): string
    {
        return match($provider) {
            'orange_money' => 'orange_money',
            'moov_money'   => 'moov_money',
            default        => 'mobile_money',
        };
    }
}
