<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Service de gestion des périodes de paie.
 *
 * Centralise :
 * - La création/résolution de la période pour un mois donné
 * - Le guard de sécurité (bloque les écritures sur périodes verrouillées)
 * - Les transitions de statut avec journalisation
 * - Le rattachement en masse des bulletins orphelins à leur période
 */
class PayrollPeriodService
{
    // ── Résolution de période ─────────────────────────────────────────────────

    /**
     * Retourne (ou crée) la période correspondant à une date donnée.
     * Utilisé par BulletinNumberingService et le calcul de paie.
     */
    public function resolveForDate(Carbon $date, ?int $companyId = null): PayrollPeriod
    {
        $companyId ??= Company::firstOrFail()->id;
        return PayrollPeriod::findOrCreateForMonth($date, $companyId);
    }

    // ── Guard de sécurité ─────────────────────────────────────────────────────

    /**
     * Lance une RuntimeException si la période est verrouillée ou archivée.
     * À appeler avant toute écriture sur PayrollItem.
     *
     * Comportement si $period est null :
     * → Le bulletin n'a pas de période assignée (données antérieures à Phase 4)
     * → On laisse passer (règle de non-régression).
     */
    public function guardAgainstLocked(?PayrollPeriod $period): void
    {
        if ($period === null) {
            return; // bulletins sans période = toujours modifiables
        }
        $period->guardAgainstWrite();
    }

    /**
     * Guard rapide par item : résout la période de l'item puis vérifie.
     */
    public function guardItem(PayrollItem $item): void
    {
        $this->guardAgainstLocked($item->period);
    }

    // ── Transitions ───────────────────────────────────────────────────────────

    /**
     * Clôture la période après vérification qu'aucun run de paie n'est en cours.
     */
    public function closePeriod(PayrollPeriod $period): void
    {
        if (! $period->isOpen()) {
            throw new \LogicException("La période « {$period->libelle} » n'est pas ouverte.");
        }
        $period->close(Auth::id());
    }

    /**
     * Réouvre une période clôturée.
     */
    public function reopenPeriod(PayrollPeriod $period): void
    {
        $period->reopen(Auth::id());
    }

    /**
     * Verrouille définitivement la période.
     * Si elle est encore ouverte, la clôture automatiquement d'abord.
     */
    public function lockPeriod(PayrollPeriod $period): void
    {
        $period->lock(Auth::id());
    }

    /**
     * Déverrouille la période (action dangereuse, tracée).
     */
    public function unlockPeriod(PayrollPeriod $period, string $reason): void
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('La justification est obligatoire.');
        }
        $period->unlock($reason, Auth::id());
    }

    /**
     * Archive une période verrouillée (état terminal).
     */
    public function archivePeriod(PayrollPeriod $period): void
    {
        $period->archive(Auth::id());
    }

    // ── Rattachement de bulletins ─────────────────────────────────────────────

    /**
     * Rattache à leur période tous les PayrollItems d'un run qui n'en ont pas.
     * Idempotent — peut être relancé sans effet de bord.
     *
     * @param  int    $runId
     * @param  Carbon $date   La date du mois de la période
     * @return int   Nombre de bulletins mis à jour
     */
    public function attachRunToPeriod(int $runId, Carbon $date): int
    {
        $companyId = Company::firstOrFail()->id;
        $period    = $this->resolveForDate($date, $companyId);

        // Guard : ne pas rattacher si la période est déjà verrouillée
        $this->guardAgainstLocked($period);

        return PayrollItem::where('payroll_run_id', $runId)
                          ->whereNull('payroll_period_id')
                          ->update(['payroll_period_id' => $period->id]);
    }

    // ── Statistiques ─────────────────────────────────────────────────────────

    /**
     * Résumé rapide de toutes les périodes d'une société, groupées par année.
     */
    public function summaryByYear(int $companyId): \Illuminate\Support\Collection
    {
        return PayrollPeriod::where('company_id', $companyId)
            ->withCount('items')
            ->orderByDesc('period_start')
            ->get()
            ->groupBy(fn (PayrollPeriod $p) => $p->period_start->year);
    }
}
