<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOrderOperation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * [PRODUCTION] Gammes opératoires → Work Orders.
 * Instancie les opérations d'un OF depuis la gamme de sa nomenclature,
 * pilote leur exécution (début/fin) et calcule l'avancement de l'OF.
 */
class RoutingService
{
    /** Génère les Work Orders d'un OF depuis la gamme de sa nomenclature. */
    public function generateWorkOrders(ProductionOrder $order): int
    {
        if ($order->operations()->exists()) {
            throw ValidationException::withMessages(['operations' => 'Les opérations de cet OF ont déjà été générées.']);
        }

        $order->loadMissing('billOfMaterial.routing.operations.workCenter');
        $routing = $order->billOfMaterial?->routing;
        if (! $routing || $routing->operations->isEmpty()) {
            throw ValidationException::withMessages(['routing' => 'Aucune gamme opératoire active sur la nomenclature de cet OF.']);
        }

        $qty = (float) ($order->quantity_requested ?: 1);

        return DB::transaction(function () use ($order, $routing, $qty) {
            $n = 0;
            foreach ($routing->operations as $op) {
                $planned = (float) $op->setup_minutes + (float) $op->run_minutes_per_unit * $qty;
                $order->operations()->create([
                    'company_id'           => $order->company_id,
                    'routing_operation_id' => $op->id,
                    'work_center_id'       => $op->work_center_id,
                    'sequence'             => $op->sequence,
                    'name'                 => $op->name,
                    'planned_minutes'      => round($planned, 2),
                    'real_minutes'         => 0,
                    'status'               => 'pending',
                    'created_by'           => Auth::id(),
                ]);
                $n++;
            }

            return $n;
        });
    }

    public function start(ProductionOrderOperation $op): void
    {
        if ($op->status === 'done') {
            throw ValidationException::withMessages(['status' => 'Opération déjà terminée.']);
        }
        $op->update(['status' => 'in_progress', 'started_at' => $op->started_at ?? now()]);
    }

    public function finish(ProductionOrderOperation $op, ?float $realMinutes = null): void
    {
        if ($op->status === 'done') {
            return;
        }
        $minutes = $realMinutes;
        if ($minutes === null && $op->started_at) {
            $minutes = round($op->started_at->diffInSeconds(now()) / 60, 2);
        }
        $op->update([
            'status'       => 'done',
            'ended_at'     => now(),
            'started_at'   => $op->started_at ?? now(),
            'real_minutes' => $minutes ?? $op->planned_minutes,
        ]);
    }

    /** Avancement de l'OF en % (opérations terminées / total). */
    public function progress(ProductionOrder $order): array
    {
        $ops   = $order->operations()->get();
        $total = $ops->count();
        $done  = $ops->where('status', 'done')->count();

        return [
            'total'    => $total,
            'done'     => $done,
            'percent'  => $total > 0 ? (int) round($done / $total * 100) : 0,
            'planned'  => (float) $ops->sum('planned_minutes'),
            'real'     => (float) $ops->sum('real_minutes'),
        ];
    }
}
