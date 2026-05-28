<?php

namespace App\Services;

use App\Models\ClientPaymentSchedule;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class ClientPaymentScheduleService
{
    /**
     * Crée un échéancier depuis des tranches en % + jours.
     * $installments = [['percent'=>30,'days_after'=>0,'label'=>'Acompte'], ...]
     */
    public function createFromInstallments(Invoice $invoice, array $installments): void
    {
        DB::transaction(function () use ($invoice, $installments) {
            // Delete existing schedule
            ClientPaymentSchedule::where('invoice_id', $invoice->id)->delete();

            // [FIX-WITHHOLDING-SCH] Use net_to_pay for schedule amounts, not total_ttc.
            // When there is a retenue à la source, the client only pays the net amount.
            $base     = $invoice->issued_at ?? today();
            $netBasis = (int) ($invoice->net_to_pay ?: $invoice->total_ttc);
            $number   = 1;

            foreach ($installments as $inst) {
                $percent  = (float) ($inst['percent'] ?? 0);
                $days     = (int)   ($inst['days_after'] ?? 0);
                $amount   = (int) round($netBasis * $percent / 100);

                ClientPaymentSchedule::create([
                    'invoice_id'          => $invoice->id,
                    'installment_number'  => $number++,
                    'due_date'            => $base->copy()->addDays($days),
                    'amount'              => $amount,
                    'paid_amount'         => 0,
                    'status'              => 'en_attente',
                    'label'               => $inst['label'] ?? "Échéance {$number}",
                ]);
            }
        });
    }

    /**
     * Crée un échéancier custom (dates + montants explicites).
     * $rows = [['due_date'=>'2026-06-01','amount'=>50000,'label'=>'...'], ...]
     */
    public function createCustom(Invoice $invoice, array $rows): void
    {
        DB::transaction(function () use ($invoice, $rows) {
            $netBasis    = (int) ($invoice->net_to_pay ?: $invoice->total_ttc);
            $totalAmount = array_sum(array_column($rows, 'amount'));
            if (abs($totalAmount - $netBasis) > 1) {
                throw new \RuntimeException(
                    'Le total des échéances (' . number_format($totalAmount, 0, ',', ' ') . ' FCFA) '
                    . 'ne correspond pas au net à payer de la facture (' . number_format($netBasis, 0, ',', ' ') . ' FCFA).'
                );
            }

            ClientPaymentSchedule::where('invoice_id', $invoice->id)->delete();

            foreach ($rows as $i => $row) {
                ClientPaymentSchedule::create([
                    'invoice_id'         => $invoice->id,
                    'installment_number' => $i + 1,
                    'due_date'           => $row['due_date'],
                    'amount'             => (int) $row['amount'],
                    'paid_amount'        => 0,
                    'status'             => 'en_attente',
                    'label'              => $row['label'] ?? 'Échéance ' . ($i + 1),
                ]);
            }
        });
    }

    /**
     * Met à jour le statut de l'échéance quand un paiement partiel est reçu.
     */
    public function markPayment(ClientPaymentSchedule $schedule, int $amount): void
    {
        DB::transaction(function () use ($schedule, $amount) {
            $newPaid      = min((int) $schedule->amount, (int) $schedule->paid_amount + $amount);
            $remaining    = (int) $schedule->amount - $newPaid;
            $schedule->update([
                'paid_amount' => $newPaid,
                'status'      => $remaining <= 0 ? 'paye' : 'partiel',
            ]);
        });
    }
}
