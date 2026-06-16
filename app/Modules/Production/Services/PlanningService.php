<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\ProductionOrderOperation;
use App\Modules\Production\Models\WorkCenter;

/**
 * [PRODUCTION] Plan de charge — capacité vs charge planifiée par centre de travail.
 *
 * Charge = somme des temps prévus des opérations (Work Orders) non terminées,
 * sur des OF actifs (lancés / en cours), rattachées au centre.
 * Capacité = capacité journalière × rendement × horizon (jours).
 */
class PlanningService
{
    public function loadByWorkCenter(int $horizonDays = 7): array
    {
        $centers = WorkCenter::where('is_active', true)->orderBy('name')->get();

        // Charge prévue (minutes) par centre, opérations non terminées d'OF actifs
        $load = ProductionOrderOperation::query()
            ->whereNotNull('work_center_id')
            ->where('status', '!=', 'done')
            ->whereHas('productionOrder', fn ($q) => $q->whereIn('status', ['lance', 'en_cours']))
            ->selectRaw('work_center_id, SUM(planned_minutes) m, COUNT(*) n')
            ->groupBy('work_center_id')
            ->get()->keyBy('work_center_id');

        $rows = $centers->map(function ($wc) use ($load, $horizonDays) {
            $planned   = (float) ($load[$wc->id]->m ?? 0);
            $opsCount  = (int) ($load[$wc->id]->n ?? 0);
            $capacity  = (float) $wc->capacity_hours_per_day * 60 * ((float) $wc->efficiency_rate / 100) * $horizonDays;
            $occupation = $capacity > 0 ? round($planned / $capacity * 100, 1) : ($planned > 0 ? 100 : 0);

            $status = match (true) {
                $occupation > 100 => 'surcharge',
                $occupation >= 80 => 'charge',
                $planned <= 0     => 'libre',
                default           => 'ok',
            };

            return [
                'id'         => $wc->id,
                'name'       => $wc->name,
                'code'       => $wc->code,
                'ops'        => $opsCount,
                'planned_h'  => round($planned / 60, 1),
                'capacity_h' => round($capacity / 60, 1),
                'occupation' => $occupation,
                'status'     => $status,
            ];
        })->values();

        return [
            'horizon'    => $horizonDays,
            'rows'       => $rows,
            'overloaded' => $rows->where('status', 'surcharge')->count(),
            'total_planned_h' => round($rows->sum('planned_h'), 1),
            'total_capacity_h' => round($rows->sum('capacity_h'), 1),
        ];
    }
}
