<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * [PROD-01 — Phase 8] Détection de conflits d'ordonnancement.
 *
 * PAS un solveur : ne replanifie rien, ne propose aucune résolution — signale
 * les chevauchements entre OF affectés à la même ligne de production sur la
 * même fenêtre horaire. La décision reste humaine ; le système ne doit
 * jamais laisser croire qu'un plan sans conflit existe quand ce n'est pas
 * le cas (ProductionPlanningController::replan() ne vérifiait jusqu'ici que
 * la disponibilité de la ligne, jamais les OF déjà présents dessus).
 */
class SchedulingConflictService
{
    private const ACTIVE = ['brouillon', 'matiere_allouee', 'attente_chef', 'attente_responsable', 'lance', 'en_cours', 'suspendu'];

    /**
     * Tous les conflits actuels, tous OF actifs planifiés confondus.
     *
     * @return Collection<int, array<string,mixed>>
     */
    public function detect(): Collection
    {
        $orders = ProductionOrder::whereIn('status', self::ACTIVE)
            ->whereNotNull('production_line_id')
            ->whereNotNull('date_debut_prevue')
            ->whereNotNull('date_fin_prevue')
            ->with('productionLine:id,name,machine_id')
            ->get(['id', 'number', 'production_line_id', 'date_debut_prevue', 'date_fin_prevue', 'heure_debut_prevue', 'heure_fin_prevue']);

        return $this->pairwiseConflicts($orders);
    }

    /** Conflits impliquant un OF précis (pour l'avertissement au moment de replan()). */
    public function detectForOrder(ProductionOrder $order): Collection
    {
        return $this->detect()->filter(fn ($c) => $c['of_a']->id === $order->id || $c['of_b']->id === $order->id)->values();
    }

    /** @return Collection<int, array<string,mixed>> */
    private function pairwiseConflicts(Collection $orders): Collection
    {
        $conflicts = collect();

        foreach ($orders->groupBy('production_line_id') as $lineId => $group) {
            $windows = $group->map(fn ($o) => [
                'of' => $o,
                'start' => $this->boundary($o->date_debut_prevue, $o->heure_debut_prevue, false),
                'end' => $this->boundary($o->date_fin_prevue, $o->heure_fin_prevue, true),
            ])->values();

            for ($i = 0; $i < $windows->count(); $i++) {
                for ($j = $i + 1; $j < $windows->count(); $j++) {
                    $a = $windows[$i];
                    $b = $windows[$j];
                    $overlapStart = $a['start']->max($b['start']);
                    $overlapEnd = $a['end']->min($b['end']);

                    if ($overlapStart->lt($overlapEnd)) {
                        $line = $a['of']->productionLine;
                        $conflicts->push([
                            'production_line_id' => $lineId,
                            'resource_label' => $line?->name ?? ('Ligne #'.$lineId),
                            'of_a' => $a['of'],
                            'of_b' => $b['of'],
                            'overlap_start' => $overlapStart,
                            'overlap_end' => $overlapEnd,
                            'overlap_minutes' => $overlapStart->diffInMinutes($overlapEnd),
                        ]);
                    }
                }
            }
        }

        return $conflicts->values();
    }

    private function boundary(?string $date, ?string $time, bool $isEnd): Carbon
    {
        $d = Carbon::parse($date);
        if ($time) {
            $parts = array_pad(explode(':', $time), 2, '0');

            return $d->setTime((int) $parts[0], (int) $parts[1]);
        }

        return $isEnd ? $d->endOfDay() : $d->startOfDay();
    }
}
