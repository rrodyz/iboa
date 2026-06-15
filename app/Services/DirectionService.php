<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\Invoice;
use App\Modules\Production\Models\ProductionConsumption;
use App\Modules\Production\Models\ProductionCost;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use App\Modules\Production\Models\ProductionWaste;
use Illuminate\Support\Carbon;

/**
 * [DIRECTION] Synthèse exécutive cross-module : CA, marge, production,
 * trésorerie, impayés — agrégat lecture seule pour le tableau de bord Direction.
 */
class DirectionService
{
    public function __construct(private SalesInsightsService $sales) {}

    public function kpis(): array
    {
        $from = Carbon::now()->startOfMonth();
        $to   = Carbon::now()->endOfMonth();

        // Ventes / CA
        $salesKpis = $this->sales->dashboardKpis();
        $caMonth   = (int) ($salesKpis['ca_month'] ?? 0);

        // Impayés clients
        $impayes = Invoice::whereIn('status', ['emise', 'en_retard', 'partiellement_payee']);
        $impayesCount   = (clone $impayes)->count();
        $impayesMontant = (int) (clone $impayes)->sum('remaining_amount');

        // Trésorerie (solde comptes actifs)
        $tresorerie = (int) CashAccount::where('is_active', true)->sum('current_balance');

        // Production
        $ofEnCours   = ProductionOrder::whereIn('status', ['lance', 'en_cours'])->count();
        $ofTermine   = ProductionOrder::where('status', 'termine')->whereBetween('finished_at', [$from, $to])->count();
        $metersMonth = (float) ProductionOutput::whereBetween('produced_at', [$from, $to])->sum('total_meters');

        $consumed = (float) ProductionConsumption::whereBetween('consumed_at', [$from, $to])->sum('weight_consumed');
        $waste    = (float) ProductionWaste::whereHas('productionOrder', fn ($q) => $q->whereBetween('updated_at', [$from, $to]))->sum('weight');
        $rendement = $consumed > 0 ? round((($consumed - $waste) / $consumed) * 100, 1) : null;

        // Marge de production réalisée (OF terminés du mois)
        $marge = (int) ProductionCost::whereHas('productionOrder', fn ($q) => $q->where('status', 'termine')->whereBetween('finished_at', [$from, $to]))->sum('margin');

        return [
            'ca_month'         => $caMonth,
            'marge_month'      => $marge,
            'tresorerie'       => $tresorerie,
            'impayes_count'    => $impayesCount,
            'impayes_montant'  => $impayesMontant,
            'of_en_cours'      => $ofEnCours,
            'of_termine_month' => $ofTermine,
            'meters_month'     => $metersMonth,
            'rendement'        => $rendement,
            'waste_month'      => $waste,
        ];
    }
}
