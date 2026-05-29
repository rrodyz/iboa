<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PayrollBulletinTemplate;
use App\Models\PayrollItem;
use App\Models\PayrollNumbering;
use App\Models\PayrollNumberingSequence;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service de numérotation atomique des bulletins de paie.
 *
 * Garantit l'unicité des numéros même en cas de calcul parallèle
 * (plusieurs bulletins générés simultanément pour le même exercice).
 *
 * Principe : SELECT FOR UPDATE sur la ligne de séquence →
 *            incrément → formatage → libération du verrou.
 */
class BulletinNumberingService
{
    /**
     * Génère et assigne un numéro de bulletin à un PayrollItem.
     * Utilise la règle de numérotation par défaut si aucune n'est passée.
     *
     * @param  PayrollItem       $item    L'item bulletin à numéroter
     * @param  Carbon            $period  La période de paie (ex: 2026-05-01)
     * @param  PayrollNumbering|null $rule  Règle spécifique (null = défaut de l'entreprise)
     * @return string            Le numéro attribué
     */
    public function assign(PayrollItem $item, Carbon $period, ?PayrollNumbering $rule = null): string
    {
        $company = currentCompany();

        $rule ??= PayrollNumbering::where('company_id', $company->id)
                                  ->where('is_active', true)
                                  ->where('is_default', true)
                                  ->first();

        if (! $rule) {
            // Aucune règle configurée → numéro brut non formaté
            $number = 'BUL-' . $period->format('Y-m') . '-' . str_pad($item->id, 4, '0', STR_PAD_LEFT);
            $item->update(['bulletin_number' => $number]);
            return $number;
        }

        $number = DB::transaction(function () use ($rule, $period) {
            $periodKey = $rule->periodKey($period);

            // Crée la ligne si absente, puis verrouille pour l'UPDATE
            $sequence = PayrollNumberingSequence::firstOrCreate(
                ['numbering_id' => $rule->id, 'period_key' => $periodKey],
                ['last_seq' => 0]
            );

            // Lock exclusif → bloque les transactions concurrentes
            $sequence = PayrollNumberingSequence::where('id', $sequence->id)
                                                ->lockForUpdate()
                                                ->first();

            $sequence->increment('last_seq');
            $sequence->refresh();

            return $rule->buildNumber($period, $sequence->last_seq);
        });

        $item->update([
            'bulletin_number' => $number,
            'numbering_id'    => $rule->id,
        ]);

        return $number;
    }

    /**
     * Assigne le modèle de bulletin par défaut à un PayrollItem.
     */
    public function assignTemplate(PayrollItem $item, ?PayrollBulletinTemplate $template = null): void
    {
        $company = currentCompany();

        $template ??= PayrollBulletinTemplate::where('company_id', $company->id)
                                              ->where('is_active', true)
                                              ->where('is_default', true)
                                              ->first();

        if ($template) {
            $item->update(['template_id' => $template->id]);
        }
    }

    /**
     * Numérotation en masse : assigne un numéro à tous les items d'un run
     * qui n'en ont pas encore. Opération idempotente.
     *
     * @param  int    $runId   ID du PayrollRun
     * @param  Carbon $period  Période de paie
     * @return int   Nombre de bulletins numérotés
     */
    public function assignBatch(int $runId, Carbon $period): int
    {
        $items = PayrollItem::where('payroll_run_id', $runId)
                            ->whereNull('bulletin_number')
                            ->get();

        $count = 0;
        foreach ($items as $item) {
            $this->assign($item, $period);
            $count++;
        }

        return $count;
    }

    /**
     * Retourne le prochain numéro qui sera assigné (sans incrémenter).
     * Utile pour l'aperçu dans l'interface.
     */
    public function peek(PayrollNumbering $rule, Carbon $period): string
    {
        $periodKey = $rule->periodKey($period);
        $sequence  = PayrollNumberingSequence::where('numbering_id', $rule->id)
                                              ->where('period_key', $periodKey)
                                              ->first();

        $nextSeq = ($sequence?->last_seq ?? 0) + 1;
        return $rule->buildNumber($period, $nextSeq);
    }
}
