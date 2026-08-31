<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\PlanningService;
use App\Modules\Production\Services\SchedulingConflictService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;

class ProductionPlanningController extends Controller
{
    public function __construct(
        private PlanningService $planning,
        private SchedulingConflictService $conflicts,
    ) {
        $this->middleware('permission:production.view');
        $this->middleware('permission:production.create')->only(['replan']);
    }

    public function index(Request $request): \Inertia\Response
    {
        $horizon = (int) $request->input('horizon', 7);
        $horizon = max(1, min(60, $horizon));

        $plan       = $this->planning->loadByWorkCenter($horizon);
        $planMachine = $this->planning->loadByMachine($horizon);
        $planTeam    = $this->planning->loadByTeam($horizon);

        // [X3 §19] Replanification : OF actifs déplaçables (dates / ligne)
        $ofActifs = ProductionOrder::with(['product:id,name', 'productionLine:id,name', 'client:id,name,trade_name'])
            ->whereIn('status', ['brouillon', 'matiere_allouee', 'attente_chef', 'attente_responsable', 'lance', 'en_cours', 'suspendu'])
            ->orderByRaw('date_fabrication_prevue IS NULL, date_fabrication_prevue')
            ->orderByDesc('id')->limit(30)->get();

        $lignes = ProductionLine::where('is_active', true)->orderBy('name')->get(['id', 'name', 'status']);

        // [PROD-01 Phase 8] Détection — pas résolution. Le planificateur décide.
        $conflits = $this->conflicts->detect();

        // [REACT-01E] Seul calcul fait ici : un ratio de présentation combinant
        // deux totaux DÉJÀ calculés par PlanningService (total_planned_h /
        // total_capacity_h) — c'est exactement ce que faisait le Blade
        // historique en tête de vue ($tauxGlobal), simplement déplacé du
        // template vers le contrôleur pour que React n'ait aucun calcul à
        // faire. Aucun terme de charge/capacité/occupation n'est recalculé.
        $tauxGlobal = $plan['total_capacity_h'] > 0
            ? round($plan['total_planned_h'] / $plan['total_capacity_h'] * 100)
            : 0;

        return Inertia::render('Production/Planning/Index', [
            'horizon' => $horizon,
            'tauxGlobal' => $tauxGlobal,
            'plan' => $this->wrapPlan($plan),
            'planMachine' => $this->wrapPlan($planMachine),
            'planTeam' => $this->wrapPlan($planTeam),
            'conflicts' => $conflits->map(fn ($c) => [
                'resourceLabel' => $c['resource_label'],
                'ofANumber' => $c['of_a']->number,
                'ofBNumber' => $c['of_b']->number,
                'overlapStart' => $c['overlap_start']->format('d/m H:i'),
                'overlapEnd' => $c['overlap_end']->format('d/m H:i'),
                'overlapMinutes' => $c['overlap_minutes'],
            ])->values(),
            'canReplan' => $request->user()->can('production.create'),
            'lignes' => $lignes->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'status' => $l->status])->values(),
            'ofActifs' => $ofActifs->map(fn ($of) => [
                'id' => $of->id,
                'number' => $of->number,
                'productName' => $of->product?->name,
                'clientName' => $of->client?->trade_name ?? $of->client?->name,
                'status' => $of->status,
                'statusLabel' => $of->statusLabel(),
                'productionLineId' => $of->production_line_id,
                'dateFabricationPrevue' => $of->date_fabrication_prevue?->format('Y-m-d'),
                'dateFinPrevue' => $of->date_fin_prevue?->format('Y-m-d'),
                // Comparaison de date pure présentation (comme le Blade
                // historique), calculée ici plutôt qu'avec l'horloge du
                // navigateur — jamais recalculée côté React.
                'enRetard' => (bool) ($of->date_fin_prevue && $of->date_fin_prevue->isPast()
                    && in_array($of->status, ['lance', 'en_cours', 'suspendu'], true)),
                'showUrl' => route('production.orders.show', $of),
                'replanUrl' => route('production.planning.replan', $of),
            ])->values(),
            'links' => [
                'downtimesUrl' => route('production.downtimes'),
                'planningUrl' => route('production.planning'),
            ],
        ]);
    }

    /** @param array{horizon:int,rows:\Illuminate\Support\Collection,overloaded:int,total_planned_h:float,total_capacity_h:float} $plan */
    private function wrapPlan(array $plan): array
    {
        return [
            'rows' => $plan['rows']->values(),
            'overloaded' => $plan['overloaded'],
            'totalPlannedH' => $plan['total_planned_h'],
            'totalCapacityH' => $plan['total_capacity_h'],
        ];
    }

    /**
     * [X3 §19] Déplacer un OF (dates prévues) et/ou le réaffecter à une autre ligne.
     * Bloqué sur OF clôturé/annulé ; ligne indisponible refusée. [PROD-01 Phase 8]
     * Un chevauchement résultant n'est jamais bloqué (pas un solveur) mais
     * signalé dans le message de retour — le planificateur reste décisionnaire.
     */
    public function replan(Request $request, ProductionOrder $order): RedirectResponse
    {
        abort_if(in_array($order->status, ['termine', 'annule'], true), 422, 'OF clôturé ou annulé — replanification impossible.');

        $data = $request->validate([
            'production_line_id'      => ['nullable', 'integer', 'exists:production_lines,id'],
            'date_fabrication_prevue' => ['nullable', 'date'],
            'date_fin_prevue'         => ['nullable', 'date', 'after_or_equal:date_fabrication_prevue'],
        ]);

        if (! empty($data['production_line_id'])) {
            $ligne = ProductionLine::findOrFail($data['production_line_id']);
            if (in_array($ligne->status, ['indisponible', 'arretee', 'en_panne'], true)) {
                return back()->with('error', 'Ligne « ' . $ligne->name . ' » indisponible — réaffectation refusée.');
            }
        }

        $order->update(array_filter($data, fn ($v) => $v !== null && $v !== ''));

        $conflitsOf = $this->conflicts->detectForOrder($order->fresh());
        if ($conflitsOf->isNotEmpty()) {
            return back()->with('warning', 'OF ' . $order->number . ' replanifié — attention, ' . $conflitsOf->count() . ' chevauchement(s) détecté(s) sur la même ligne.');
        }

        return back()->with('success', 'OF ' . $order->number . ' replanifié.');
    }
}
