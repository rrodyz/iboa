<?php

namespace App\Modules\Production\Services;

use App\Models\ProductionCalendarException;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * [PROD-01 — Phase 6] Calendrier de capacité : jours ouvrés réels sur un
 * horizon (week-ends + jours fériés déclarés exclus), à la place d'un
 * horizon plat en jours calendaires. Un horizon vendredi → lundi (3 jours
 * calendaires) ne doit compter que 1 jour ouvré, pas 3.
 */
class CapacityCalendarService
{
    /** Nombre de jours ouvrés dans [$from, $from + $horizonDays[ (borne haute exclue). */
    public function workingDaysInHorizon(CarbonInterface $from, int $horizonDays, int $companyId): int
    {
        if ($horizonDays <= 0) {
            return 0;
        }

        $holidays = $this->holidaySet($from->copy()->startOfDay(), $from->copy()->addDays($horizonDays), $companyId);

        $count = 0;
        $cursor = $from->copy()->startOfDay();
        for ($i = 0; $i < $horizonDays; $i++) {
            if ($this->isWorkingDay($cursor, $companyId, $holidays)) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }

    public function isWorkingDay(CarbonInterface $date, int $companyId, ?array $holidays = null): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        $holidays ??= $this->holidaySet($date, $date, $companyId);

        return ! in_array($date->format('Y-m-d'), $holidays, true);
    }

    /** @return array<int,string> */
    private function holidaySet(CarbonInterface $from, CarbonInterface $to, int $companyId): array
    {
        return ProductionCalendarException::where('company_id', $companyId)
            ->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->all();
    }
}
