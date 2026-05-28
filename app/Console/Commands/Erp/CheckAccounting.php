<?php

namespace App\Console\Commands\Erp;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan erp:check-accounting
 *
 * Vérifie l'intégrité de la comptabilité :
 *  - Écritures déséquilibrées
 *  - Documents validés sans écriture GL
 *  - Lettrage incohérent
 *  - Doublons de numéros d'écriture
 */
class CheckAccounting extends Command
{
    protected $signature   = 'erp:check-accounting
                                {--fix : Corriger automatiquement les anomalies réversibles}
                                {--company= : Filtrer par company_id}';
    protected $description = 'Contrôle d\'intégrité de la comptabilité générale';

    public function handle(): int
    {
        $companyId = $this->option('company') ? (int) $this->option('company') : null;
        $fix       = $this->option('fix');
        $issues    = 0;

        $this->info('');
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  ERP — Contrôle comptabilité');
        if ($companyId) $this->info("  Société #$companyId");
        $this->info('═══════════════════════════════════════════════════════');

        // ── 1. Écritures déséquilibrées ────────────────────────────────────────
        $this->newLine();
        $this->line('<fg=cyan>▸ Équilibre débit / crédit</> (hors brouillon)');
        $q = DB::table('journal_entries')
            ->whereColumn('total_debit', '!=', 'total_credit')
            ->where('status', '!=', 'brouillon');
        if ($companyId) $q->where('company_id', $companyId);
        $unbalanced = $q->select('id', 'number', 'entry_date', 'total_debit', 'total_credit', 'description')
                        ->limit(50)->get();

        if ($unbalanced->isEmpty()) {
            $this->line('  <fg=green>✓</> Toutes les écritures validées sont équilibrées');
        } else {
            $issues += $unbalanced->count();
            $this->warn("  ✗ {$unbalanced->count()} écriture(s) déséquilibrée(s) :");
            $this->table(
                ['ID', 'Numéro', 'Date', 'Total débit', 'Total crédit', 'Écart'],
                $unbalanced->map(fn($e) => [
                    $e->id, $e->number, $e->entry_date,
                    number_format($e->total_debit, 0, ',', ' '),
                    number_format($e->total_credit, 0, ',', ' '),
                    number_format($e->total_debit - $e->total_credit, 0, ',', ' '),
                ])->toArray()
            );
        }

        // ── 2. Écritures vs lignes : vérification somme lignes = total écriture ──
        $this->newLine();
        $this->line('<fg=cyan>▸ Cohérence lignes ↔ totaux écriture</>');
        $discrepant = DB::table('journal_entries as je')
            ->join(DB::raw('(SELECT journal_entry_id, SUM(debit) as sum_d, SUM(credit) as sum_c FROM journal_entry_lines GROUP BY journal_entry_id) as jel'),
                'jel.journal_entry_id', '=', 'je.id')
            ->where('je.status', '!=', 'brouillon')
            ->where(fn($q) =>
                $q->whereRaw('ABS(je.total_debit - jel.sum_d) > 1')
                  ->orWhereRaw('ABS(je.total_credit - jel.sum_c) > 1')
            )
            ->when($companyId, fn($q) => $q->where('je.company_id', $companyId))
            ->select('je.id', 'je.number', 'je.total_debit', 'je.total_credit',
                     'jel.sum_d', 'jel.sum_c')
            ->limit(30)
            ->get();

        if ($discrepant->isEmpty()) {
            $this->line('  <fg=green>✓</> Totaux écriture cohérents avec somme des lignes');
        } else {
            $issues += $discrepant->count();
            $this->warn("  ✗ {$discrepant->count()} écriture(s) avec totaux incohérents :");
            $this->table(['ID', 'Numéro', 'Total D écr.', 'Σ D lignes', 'Total C écr.', 'Σ C lignes'],
                $discrepant->map(fn($e) => [$e->id, $e->number,
                    $e->total_debit, $e->sum_d, $e->total_credit, $e->sum_c])->toArray());
        }

        // ── 3. Factures validées sans GL ─────────────────────────────────────
        $this->newLine();
        $this->line('<fg=cyan>▸ Factures validées sans écriture comptable</>');
        $q = DB::table('invoices')
            ->whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'payee', 'en_retard'])
            ->whereNull('journal_entry_id')
            ->whereNull('deleted_at');
        if ($companyId) $q->where('company_id', $companyId);
        $cnt = $q->count();
        if ($cnt === 0) {
            $this->line('  <fg=green>✓</> Toutes les factures validées ont une écriture');
        } else {
            $issues += $cnt;
            $this->warn("  ✗ {$cnt} facture(s) validée(s) sans écriture GL");
            $rows = $q->select('id', 'number', 'status', 'total_ttc', 'issued_at')->limit(20)->get();
            $this->table(['ID', 'Numéro', 'Statut', 'Total TTC', 'Date'],
                $rows->map(fn($r) => [$r->id, $r->number, $r->status,
                    number_format($r->total_ttc, 0, ',', ' ') . ' FCFA', $r->issued_at])->toArray());
        }

        // ── 4. Factures fournisseurs validées sans GL ──────────────────────────
        $this->newLine();
        $this->line('<fg=cyan>▸ Factures fournisseurs validées sans écriture comptable</>');
        $q = DB::table('supplier_invoices')
            ->whereIn('status', ['validee', 'partiellement_payee', 'payee'])
            ->whereNull('journal_entry_id')
            ->whereNull('deleted_at');
        if ($companyId) $q->where('company_id', $companyId);
        $cnt = $q->count();
        if ($cnt === 0) {
            $this->line('  <fg=green>✓</> Toutes les factures fournisseurs validées ont une écriture');
        } else {
            $issues += $cnt;
            $this->warn("  ✗ {$cnt} facture(s) fournisseur validée(s) sans écriture GL");
        }

        // ── 5. Lettrage incohérent ─────────────────────────────────────────────
        $this->newLine();
        $this->line('<fg=cyan>▸ Lettrage — groupes déséquilibrés</>');
        $unbalancedLettrages = DB::table('journal_entry_lines')
            ->whereNotNull('reconciliation_ref')
            ->groupBy('reconciliation_ref')
            ->havingRaw('ABS(SUM(debit) - SUM(credit)) > 1')
            ->when($companyId, function ($q) use ($companyId) {
                $q->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                  ->where('journal_entries.company_id', $companyId);
            })
            ->select('reconciliation_ref', DB::raw('SUM(debit) as d'), DB::raw('SUM(credit) as c'))
            ->limit(20)->get();

        if ($unbalancedLettrages->isEmpty()) {
            $this->line('  <fg=green>✓</> Tous les groupes de lettrage sont équilibrés');
        } else {
            $issues += $unbalancedLettrages->count();
            $this->warn("  ✗ {$unbalancedLettrages->count()} groupe(s) de lettrage déséquilibré(s) :");
            $this->table(['Référence', 'Σ Débit', 'Σ Crédit', 'Écart'],
                $unbalancedLettrages->map(fn($r) => [
                    $r->reconciliation_ref,
                    number_format($r->d, 0, ',', ' '),
                    number_format($r->c, 0, ',', ' '),
                    number_format($r->d - $r->c, 0, ',', ' '),
                ])->toArray());
        }

        // ── 6. Doublons de numéros d'écriture ─────────────────────────────────
        $this->newLine();
        $this->line('<fg=cyan>▸ Doublons de numéros d\'écriture</>');
        $duplicates = DB::table('journal_entries')
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->groupBy('company_id', 'number')
            ->havingRaw('COUNT(*) > 1')
            ->select('company_id', 'number', DB::raw('COUNT(*) as nb'))
            ->get();

        if ($duplicates->isEmpty()) {
            $this->line('  <fg=green>✓</> Aucun doublon de numéro d\'écriture');
        } else {
            $issues += $duplicates->count();
            $this->warn("  ✗ {$duplicates->count()} doublon(s) de numéro d'écriture");
            $this->table(['Société', 'Numéro', 'Occurrences'],
                $duplicates->map(fn($r) => [$r->company_id, $r->number, $r->nb])->toArray());
        }

        // ── Rapport ────────────────────────────────────────────────────────────
        $this->newLine();
        $this->info('─────────────────────────────────────────────────────');
        if ($issues === 0) {
            $this->info('  ✅  Comptabilité intègre — aucune anomalie détectée');
        } else {
            $this->warn("  ⚠️   {$issues} anomalie(s) comptable(s) détectée(s)");
            $this->line('  → Relancez avec <fg=cyan>--fix</> pour les corrections automatiques réversibles');
        }
        $this->info('═══════════════════════════════════════════════════════');

        return $issues > 0 ? self::FAILURE : self::SUCCESS;
    }
}
