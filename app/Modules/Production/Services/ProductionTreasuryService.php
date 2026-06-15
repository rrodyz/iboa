<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\ProductionCost;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Collection;

/**
 * [PRODUCTION → TRÉSORERIE] Prévision financière de la production.
 *
 * Agrège (lecture seule) :
 *  - besoin de financement = coûts engagés des OF actifs (lancés / en cours)
 *  - prévision achats matières = déficit MRP valorisé
 *  - marge réalisée = somme des marges des OF terminés
 */
class ProductionTreasuryService
{
    public function __construct(private MrpService $mrp) {}

    public function forecast(): array
    {
        // Coûts engagés sur OF non clôturés (lancé + en cours)
        $engaged = (int) ProductionCost::whereHas('productionOrder', fn ($q) => $q->whereIn('status', ['lance', 'en_cours']))
            ->sum('total_cost');

        // Marge réalisée (OF terminés)
        $margin = (int) ProductionCost::whereHas('productionOrder', fn ($q) => $q->where('status', 'termine'))
            ->sum('margin');

        // Besoin d'achat matières (MRP)
        $materialNeed = (int) $this->mrp->analyze()->sum('estimated');

        // Détail par OF actif
        $breakdown = ProductionOrder::with(['client', 'cost'])
            ->whereIn('status', ['lance', 'en_cours'])
            ->orderByDesc('id')->get()
            ->map(fn ($o) => [
                'number'   => $o->number,
                'client'   => $o->client?->name ?? '—',
                'status'   => $o->statusLabel(),
                'cost'     => (int) ($o->cost?->total_cost ?? 0),
            ]);

        return [
            'engaged_cost'   => $engaged,
            'material_need'  => $materialNeed,
            'realized_margin'=> $margin,
            'financing_need' => $engaged + $materialNeed,   // trésorerie à mobiliser
            'active_count'   => $breakdown->count(),
            'breakdown'      => $breakdown,
        ];
    }
}
