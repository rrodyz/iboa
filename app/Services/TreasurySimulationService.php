<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\Invoice;
use App\Models\SupplierInvoice;
use Illuminate\Support\Carbon;

/**
 * [TRESO] Simulation de trésorerie : projection what-if de la position future
 * à partir du solde actuel, des créances clients à encaisser et des dettes
 * fournisseurs à payer, modulées par des hypothèses (taux de recouvrement,
 * délai de paiement moyen, charges récurrentes). Aucune persistance.
 */
class TreasurySimulationService
{
    /**
     * @param array{horizon_weeks?:int,recovery_rate?:int,delay_days?:int,recurring_weekly?:int} $params
     * @return array{start:int,buckets:array,min_balance:int,min_week:int,total_in:int,total_out:int,end:int}
     */
    public function simulate(array $params): array
    {
        $horizon   = max(1, min(52, (int) ($params['horizon_weeks'] ?? 12)));
        $recovery  = max(0, min(100, (int) ($params['recovery_rate'] ?? 90))) / 100;
        $delayDays = max(0, (int) ($params['delay_days'] ?? 0));
        $recurring = max(0, (int) ($params['recurring_weekly'] ?? 0));

        $today = Carbon::today();
        $start = (int) CashAccount::where('is_active', true)->sum('current_balance');

        // Buckets hebdomadaires : index 0 = semaine en cours.
        $in  = array_fill(0, $horizon + 1, 0);
        $out = array_fill(0, $horizon + 1, 0);

        // ── Entrées : créances clients ouvertes (remaining > 0) × taux de recouvrement
        Invoice::query()
            ->whereNotIn('status', ['brouillon', 'payee', 'annulee'])
            ->where('remaining_amount', '>', 0)
            ->select('remaining_amount', 'due_at')
            ->get()
            ->each(function ($inv) use (&$in, $today, $horizon, $recovery, $delayDays) {
                $date = $inv->due_at ? Carbon::parse($inv->due_at)->addDays($delayDays) : $today;
                $week = $this->weekIndex($today, $date, $horizon);
                $in[$week] += (int) round($inv->remaining_amount * $recovery);
            });

        // ── Sorties : dettes fournisseurs ouvertes (remaining > 0), payées à l'échéance
        SupplierInvoice::query()
            ->whereNotIn('status', ['brouillon', 'payee', 'annulee'])
            ->where('remaining_amount', '>', 0)
            ->select('remaining_amount', 'due_at')
            ->get()
            ->each(function ($inv) use (&$out, $today, $horizon) {
                $date = $inv->due_at ? Carbon::parse($inv->due_at) : $today;
                $week = $this->weekIndex($today, $date, $horizon);
                $out[$week] += (int) $inv->remaining_amount;
            });

        // ── Charges récurrentes hebdomadaires (hypothèse) — dès la semaine 1
        if ($recurring > 0) {
            for ($w = 1; $w <= $horizon; $w++) {
                $out[$w] += $recurring;
            }
        }

        // ── Construction des buckets + solde courant
        $balance   = $start;
        $buckets   = [];
        $minBal    = $start;
        $minWeek   = 0;
        $totalIn   = 0;
        $totalOut  = 0;

        for ($w = 0; $w <= $horizon; $w++) {
            $net      = $in[$w] - $out[$w];
            $balance += $net;
            $totalIn  += $in[$w];
            $totalOut += $out[$w];

            if ($balance < $minBal) {
                $minBal  = $balance;
                $minWeek = $w;
            }

            $buckets[] = [
                'week'    => $w,
                'label'   => $w === 0 ? 'Cette sem.' : 'S+' . $w,
                'date'    => $today->copy()->addWeeks($w)->format('d/m'),
                'in'      => $in[$w],
                'out'     => $out[$w],
                'net'     => $net,
                'balance' => $balance,
            ];
        }

        return [
            'start'       => $start,
            'buckets'     => $buckets,
            'min_balance' => $minBal,
            'min_week'    => $minWeek,
            'total_in'    => $totalIn,
            'total_out'   => $totalOut,
            'end'         => $balance,
        ];
    }

    /** Indice de semaine (0..horizon) d'une date ; le passé/échu retombe en semaine 0. */
    private function weekIndex(Carbon $today, Carbon $date, int $horizon): int
    {
        if ($date->lte($today)) {
            return 0;
        }
        $week = (int) floor($today->diffInDays($date) / 7);
        return min($week, $horizon);
    }
}
