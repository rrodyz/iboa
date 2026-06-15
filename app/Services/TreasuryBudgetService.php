<?php

namespace App\Services;

use App\Models\Company;
use App\Models\TreasuryBudget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [TRESO] Budgets de trésorerie + comparaison Budget vs Réalisé.
 */
class TreasuryBudgetService
{
    public function create(array $data, array $lines = []): TreasuryBudget
    {
        return DB::transaction(function () use ($data, $lines) {
            $company = Company::findOrFail(Auth::user()->company_id);
            $budget = TreasuryBudget::create([
                'company_id'     => $company->id,
                'fiscal_year_id' => $company->current_fiscal_year_id,
                'name'           => $data['name'],
                'year'           => (int) $data['year'],
                'status'         => 'brouillon',
                'notes'          => $data['notes'] ?? null,
                'created_by'     => Auth::id(),
            ]);
            $this->syncLines($budget, $lines);
            return $budget->fresh('lines');
        });
    }

    public function syncLines(TreasuryBudget $budget, array $lines): void
    {
        $budget->lines()->delete();
        foreach ($lines as $l) {
            $category = trim((string) ($l['category'] ?? ''));
            if ($category === '') continue;
            foreach (range(1, 12) as $m) {
                $amount = (int) ($l['months'][$m] ?? 0);
                if ($amount <= 0) continue;
                $budget->lines()->create([
                    'category'       => $category,
                    'direction'      => $l['direction'] ?? 'sortie',
                    'month'          => $m,
                    'planned_amount' => $amount,
                ]);
            }
        }
    }

    /**
     * Budget vs Réalisé : pour chaque mois, prévu (lignes budget) vs réalisé
     * (encaissements confirmés / décaissements validés).
     */
    public function comparison(TreasuryBudget $budget): array
    {
        $companyId = $budget->company_id;
        $year      = $budget->year;

        // Prévu par mois / direction
        $planned = $budget->lines()
            ->selectRaw('month, direction, SUM(planned_amount) AS total')
            ->groupBy('month', 'direction')
            ->get()
            ->groupBy('direction');

        $plannedIn  = $this->byMonth($planned->get('entree'));
        $plannedOut = $this->byMonth($planned->get('sortie'));

        // Réalisé entrées : client_payments confirmés
        $realIn = DB::table('client_payments')
            ->where('company_id', $companyId)->where('status', 'confirme')
            ->whereYear('payment_date', $year)
            ->selectRaw('MONTH(payment_date) AS m, SUM(amount) AS total')
            ->groupBy('m')->pluck('total', 'm');

        // Réalisé sorties : supplier_payments validés
        $realOut = DB::table('supplier_payments')
            ->where('company_id', $companyId)
            ->where('validation_status', 'valide')
            ->whereIn('status', ['confirme'])
            ->whereYear('payment_date', $year)
            ->selectRaw('MONTH(payment_date) AS m, SUM(amount) AS total')
            ->groupBy('m')->pluck('total', 'm');

        $rows = [];
        $totals = ['plan_in'=>0,'plan_out'=>0,'real_in'=>0,'real_out'=>0];
        foreach (range(1, 12) as $m) {
            $pin = (int) ($plannedIn[$m] ?? 0);
            $pout= (int) ($plannedOut[$m] ?? 0);
            $rin = (int) ($realIn[$m] ?? 0);
            $rout= (int) ($realOut[$m] ?? 0);
            $rows[] = [
                'month'     => $m,
                'plan_in'   => $pin, 'real_in'  => $rin, 'ecart_in'  => $rin - $pin,
                'plan_out'  => $pout,'real_out' => $rout,'ecart_out' => $rout - $pout,
                'plan_net'  => $pin - $pout,
                'real_net'  => $rin - $rout,
            ];
            $totals['plan_in']+=$pin; $totals['plan_out']+=$pout; $totals['real_in']+=$rin; $totals['real_out']+=$rout;
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    private function byMonth($collection): array
    {
        $out = [];
        foreach ($collection ?? [] as $r) {
            $out[(int) $r->month] = (int) $r->total;
        }
        return $out;
    }
}
