<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\MachineMaintenance;
use App\Modules\Production\Models\ProductionMachine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * [PRODUCTION] Maintenance machines : interventions préventives / correctives,
 * disponibilité (OEE composante), MTBF / MTTR, et alertes préventif dû.
 */
class MaintenanceService
{
    /** Démarre une intervention → machine en maintenance. */
    public function start(MachineMaintenance $m): void
    {
        if ($m->status === 'termine') {
            throw ValidationException::withMessages(['status' => 'Intervention déjà terminée.']);
        }
        DB::transaction(function () use ($m) {
            $m->update(['status' => 'en_cours', 'started_at' => $m->started_at ?? now()]);
            $m->machine?->update(['status' => 'maintenance']);
        });
    }

    /** Clôture une intervention → temps d'arrêt + coût, machine réactivée. */
    public function finish(MachineMaintenance $m, ?float $downtimeMinutes = null, ?int $cost = null): void
    {
        if ($m->status === 'termine') {
            return;
        }
        $downtime = $downtimeMinutes;
        if ($downtime === null && $m->started_at) {
            $downtime = round($m->started_at->diffInSeconds(now()) / 60, 2);
        }
        DB::transaction(function () use ($m, $downtime, $cost) {
            $m->update([
                'status'           => 'termine',
                'ended_at'         => now(),
                'started_at'       => $m->started_at ?? now(),
                'downtime_minutes' => $downtime ?? 0,
                'cost'             => $cost ?? $m->cost,
            ]);
            $m->machine?->update(['status' => 'active']);
        });
    }

    /** KPI disponibilité / MTBF / MTTR d'une machine sur une période. */
    public function machineKpis(ProductionMachine $machine, int $periodDays = 30): array
    {
        $from = Carbon::now()->subDays($periodDays);
        $periodMinutes = $periodDays * 24 * 60;

        $done = MachineMaintenance::where('machine_id', $machine->id)
            ->where('status', 'termine')
            ->where('ended_at', '>=', $from)->get();

        $downtime    = (float) $done->sum('downtime_minutes');
        $corrective  = $done->where('type', 'corrective');
        $failures    = $corrective->count();
        $corrDowntime = (float) $corrective->sum('downtime_minutes');
        $uptime      = max(0, $periodMinutes - $downtime);

        return [
            'availability' => $periodMinutes > 0 ? round($uptime / $periodMinutes * 100, 1) : 100,
            'downtime_h'   => round($downtime / 60, 1),
            'failures'     => $failures,
            'mtbf_h'       => $failures > 0 ? round($uptime / 60 / $failures, 1) : null,   // temps moyen entre pannes
            'mttr_h'       => $failures > 0 ? round($corrDowntime / 60 / $failures, 1) : null, // temps moyen de réparation
            'cost'         => (int) $done->sum('cost'),
        ];
    }

    /** Machines dont la maintenance préventive est due. */
    public function dueList(): array
    {
        return ProductionMachine::whereNotNull('maintenance_frequency_days')
            ->where('maintenance_frequency_days', '>', 0)
            ->where('is_active', true)
            ->get()
            ->filter(function ($machine) {
                $last = MachineMaintenance::where('machine_id', $machine->id)
                    ->where('type', 'preventive')->where('status', 'termine')
                    ->max('ended_at');
                $due = $last
                    ? Carbon::parse($last)->addDays((int) $machine->maintenance_frequency_days)
                    : Carbon::now()->subDay();

                return $due->lte(now());
            })
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'code' => $m->code, 'frequency' => $m->maintenance_frequency_days])
            ->values()->all();
    }
}
