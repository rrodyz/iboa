<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionTimeLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * [PRODUCTION ↔ RH/PAIE] Pointage du temps opérateur sur les OF.
 * Fournit le coût de main-d'œuvre RÉEL consommé par la production.
 */
class LaborService
{
    public function log(ProductionOrder $order, array $data): ProductionTimeLog
    {
        if (in_array($order->status, ['brouillon', 'annule'], true)) {
            throw ValidationException::withMessages(['status' => 'Pointage impossible sur un OF brouillon ou annulé.']);
        }

        $hours      = (float) ($data['hours'] ?? 0);
        $hourlyCost = (float) ($data['hourly_cost'] ?? 0);
        if ($hours <= 0) {
            throw ValidationException::withMessages(['hours' => 'Le nombre d\'heures doit être positif.']);
        }

        return $order->timeLogs()->create([
            'company_id'  => $order->company_id,
            'employee_id' => $data['employee_id'] ?? null,
            'hours'       => $hours,
            'hourly_cost' => $hourlyCost,
            'labor_cost'  => (int) round($hours * $hourlyCost),
            'is_overtime' => (bool) ($data['is_overtime'] ?? false),
            'entry_date'  => $data['entry_date'] ?? now(),
            'notes'       => $data['notes'] ?? null,
            'created_by'  => Auth::id(),
        ]);
    }

    public function delete(ProductionTimeLog $log): void
    {
        $log->delete();
    }

    /** Coût MO réel total pointé sur l'OF (FCFA). */
    public function totalLaborCost(ProductionOrder $order): int
    {
        return (int) $order->timeLogs()->sum('labor_cost');
    }

    public function totalHours(ProductionOrder $order): float
    {
        return (float) $order->timeLogs()->sum('hours');
    }
}
