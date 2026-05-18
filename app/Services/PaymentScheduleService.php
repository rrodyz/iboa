<?php

namespace App\Services;

use App\Models\PaymentSchedule;
use App\Models\SupplierInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * [ACHATS-PRO-SCHEDULE] Gestion des cadenciers de paiement.
 *
 *   createFromInstallments() : crée N échéances à partir d'un tableau de pourcentages+délais
 *   createCustom()           : crée N échéances avec montants & dates fournis
 *   applyPayment()           : impute un paiement par ordre chronologique des échéances
 *   recomputeStatuses()      : recalcule les statuts (en_retard, etc.)
 */
class PaymentScheduleService
{
    /**
     * Crée un cadencier à partir de plusieurs lignes "pourcentage + délai_jours".
     *
     * @param array<int, array{percent:float, days_after:int, label?:string}> $installments
     */
    public function createFromInstallments(SupplierInvoice $invoice, array $installments): array
    {
        return DB::transaction(function () use ($invoice, $installments) {
            // Supprime les anciennes échéances non payées
            $invoice->paymentSchedules()->where('paid_amount', 0)->delete();
            if ($invoice->paymentSchedules()->exists()) {
                throw new \RuntimeException('Des paiements ont déjà été imputés au cadencier — impossible de le régénérer.');
            }

            $total = (float) $invoice->total_ttc;
            $sumPct = collect($installments)->sum('percent');
            if (abs($sumPct - 100) > 0.01) {
                throw new \RuntimeException("La somme des pourcentages doit être 100% (actuelle : {$sumPct}%).");
            }

            $base = $invoice->due_at ?? $invoice->received_at ?? now();
            $base = Carbon::parse($base);

            $created = [];
            $allocated = 0;
            foreach ($installments as $i => $line) {
                $isLast = $i === count($installments) - 1;
                $amount = $isLast
                    ? round($total - $allocated, 2)              // dernier = ajustement au centime
                    : round($total * $line['percent'] / 100, 2);
                $allocated += $amount;

                $created[] = PaymentSchedule::create([
                    'supplier_invoice_id' => $invoice->id,
                    'installment_number'  => $i + 1,
                    'due_date'            => $base->copy()->addDays((int) $line['days_after'])->toDateString(),
                    'amount'              => $amount,
                    'paid_amount'         => 0,
                    'status'              => 'en_attente',
                    'label'               => $line['label'] ?? sprintf('Échéance %d (%s%% à %d j)', $i + 1, $line['percent'], $line['days_after']),
                ]);
            }

            return $created;
        });
    }

    /**
     * Crée un cadencier avec des montants & dates explicites.
     *
     * @param array<int, array{due_date:string, amount:float, label?:string}> $rows
     */
    public function createCustom(SupplierInvoice $invoice, array $rows): array
    {
        return DB::transaction(function () use ($invoice, $rows) {
            $invoice->paymentSchedules()->where('paid_amount', 0)->delete();
            if ($invoice->paymentSchedules()->exists()) {
                throw new \RuntimeException('Des paiements ont déjà été imputés au cadencier — impossible de le régénérer.');
            }

            $sum = collect($rows)->sum('amount');
            if (abs($sum - (float) $invoice->total_ttc) > 1) {
                throw new \RuntimeException(sprintf(
                    'La somme des échéances (%s) doit égaler le total TTC de la facture (%s).',
                    number_format($sum, 0, ',', ' '),
                    number_format($invoice->total_ttc, 0, ',', ' ')
                ));
            }

            $created = [];
            foreach ($rows as $i => $line) {
                $created[] = PaymentSchedule::create([
                    'supplier_invoice_id' => $invoice->id,
                    'installment_number'  => $i + 1,
                    'due_date'            => $line['due_date'],
                    'amount'              => $line['amount'],
                    'paid_amount'         => 0,
                    'status'              => 'en_attente',
                    'label'               => $line['label'] ?? sprintf('Échéance %d', $i + 1),
                ]);
            }
            return $created;
        });
    }

    /**
     * Impute un paiement à un cadencier, ligne par ligne en ordre chronologique.
     * Retourne le reliquat non imputé (si paiement > total échéances restantes).
     */
    public function applyPayment(SupplierInvoice $invoice, float $amount): float
    {
        return DB::transaction(function () use ($invoice, $amount) {
            $schedules = $invoice->paymentSchedules()
                ->whereIn('status', ['en_attente', 'partiel'])
                ->orderBy('due_date')
                ->get();

            $remaining = $amount;
            foreach ($schedules as $sch) {
                if ($remaining <= 0) break;

                $needed = (float) ($sch->amount - $sch->paid_amount);
                $apply  = min($remaining, $needed);

                $newPaid = (float) $sch->paid_amount + $apply;
                $sch->update([
                    'paid_amount' => $newPaid,
                    'status'      => $newPaid >= (float) $sch->amount - 0.01 ? 'paye' : 'partiel',
                ]);

                $remaining -= $apply;
            }

            return $remaining;
        });
    }

    /**
     * Recalcule les statuts en_retard (si une échéance a passé sa due_date sans
     * être payée). Idéalement appelé via le scheduler quotidien.
     */
    public function recomputeOverdueFlags(): int
    {
        // On ne touche pas le status enum — on s'appuie sur l'helper isOverdue() côté modèle.
        // Cette méthode existe pour symétrie & extensions futures (par ex. notifications).
        return 0;
    }
}
