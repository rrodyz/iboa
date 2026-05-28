<?php

namespace App\Console\Commands\Erp;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan erp:backfill-journal-links
 *
 * [AUDIT-ERP-BACKFILL] Lie les documents financiers existants à leurs
 * écritures comptables via le numéro de référence.
 *
 * Concerne les données créées AVANT l'ajout de la colonne journal_entry_id
 * (migration 2026_05_26_120000). Les nouvelles opérations sont automatiquement
 * liées par AccountingService::post*().
 *
 * Logique : pour chaque document sans journal_entry_id, cherche dans journal_entries
 * la ligne dont reference = document.number. Si une seule correspondance → lie.
 * Si plusieurs → signale l'ambiguïté mais ne touche pas.
 *
 * Mode lecture seule par défaut. Passer --apply pour écrire.
 */
class BackfillJournalLinks extends Command
{
    protected $signature   = 'erp:backfill-journal-links {--apply : Appliquer les liens}';
    protected $description = 'Lie les documents financiers existants à leurs écritures GL (backfill)';

    private int $linked    = 0;
    private int $ambiguous = 0;
    private int $notFound  = 0;

    public function handle(): int
    {
        $apply = $this->option('apply');

        $this->info('');
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  ERP — Backfill liens document ↔ écriture GL');
        if (! $apply) {
            $this->warn('  Mode simulation (--apply pour écrire)');
        }
        $this->info('═══════════════════════════════════════════════════════');

        // ── Factures clients ──────────────────────────────────────────────────
        $this->backfillTable('invoices', 'number', $apply, 'Factures clients');

        // ── Factures fournisseurs ─────────────────────────────────────────────
        $this->backfillTable('supplier_invoices', 'number', $apply, 'Factures fournisseurs');

        // ── Encaissements ─────────────────────────────────────────────────────
        $this->backfillTable('client_payments', 'number', $apply, 'Encaissements clients');

        // ── Paiements fournisseurs ────────────────────────────────────────────
        $this->backfillTable('supplier_payments', 'number', $apply, 'Paiements fournisseurs');

        // ── Rapport ────────────────────────────────────────────────────────────
        $this->newLine();
        $this->info('─────────────────────────────────────────────────────');
        $verb = $apply ? 'liés' : 'identifiés';
        $this->info("  Documents {$verb}   : {$this->linked}");
        if ($this->ambiguous > 0) {
            $this->warn("  Ambiguïtés         : {$this->ambiguous} (plusieurs écritures pour un doc — vérification manuelle)");
        }
        if ($this->notFound > 0) {
            $this->line("  Non trouvés        : {$this->notFound} (écriture GL absente ou référence différente)");
        }
        if (! $apply && $this->linked > 0) {
            $this->line('');
            $this->line('  → Relancez avec <fg=cyan>--apply</> pour appliquer les liens');
        }
        $this->info('═══════════════════════════════════════════════════════');

        return self::SUCCESS;
    }

    private function backfillTable(string $table, string $refCol, bool $apply, string $label): void
    {
        $this->newLine();
        $this->line("<fg=cyan>▸ {$label}</>");

        $unlinked = DB::table($table)
            ->whereNull('journal_entry_id')
            ->whereNull('deleted_at')
            ->whereNotNull($refCol)
            ->select('id', $refCol . ' as ref', 'company_id')
            ->get();

        if ($unlinked->isEmpty()) {
            $this->line('  <fg=green>✓</> Tous déjà liés');
            return;
        }

        $this->line("  {$unlinked->count()} document(s) sans lien GL...");

        foreach ($unlinked as $doc) {
            $entries = DB::table('journal_entries')
                ->where('reference', $doc->ref)
                ->where('company_id', $doc->company_id)
                ->pluck('id');

            if ($entries->count() === 0) {
                $this->notFound++;
                $this->line("  <fg=yellow>?</> #{$doc->id} {$doc->ref} → aucune écriture trouvée");
            } elseif ($entries->count() > 1) {
                $this->ambiguous++;
                $this->line("  <fg=yellow>!</> #{$doc->id} {$doc->ref} → {$entries->count()} écritures (ambigu)");
            } else {
                $this->linked++;
                $this->line("  <fg=green>→</> #{$doc->id} {$doc->ref} ↔ JE#{$entries->first()}");
                if ($apply) {
                    DB::table($table)->where('id', $doc->id)
                        ->update(['journal_entry_id' => $entries->first()]);
                }
            }
        }
    }
}
