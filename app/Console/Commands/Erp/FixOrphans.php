<?php

namespace App\Console\Commands\Erp;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan erp:fix-orphans
 *
 * Détecte (et peut supprimer soft) les enregistrements orphelins :
 *  - Lignes de factures sans facture parente
 *  - Allocations de paiement sans facture
 *  - Lignes d'écriture sans écriture parente
 *  - Lignes de BL sans BL parent
 *  - Lignes de réception sans réception parente
 *
 * Par défaut : mode lecture seule (--dry-run implicite).
 * Passer --apply pour supprimer les orphelins.
 */
class FixOrphans extends Command
{
    protected $signature   = 'erp:fix-orphans
                                {--apply : Appliquer les suppressions (défaut : lecture seule)}';
    protected $description = 'Détecte et corrige les enregistrements orphelins';

    private bool  $apply  = false;
    private int   $found  = 0;
    private int   $fixed  = 0;

    public function handle(): int
    {
        $this->apply = $this->option('apply');

        $this->info('');
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  ERP — Nettoyage des orphelins');
        if (! $this->apply) {
            $this->warn('  Mode lecture seule (--apply pour corriger)');
        } else {
            $this->warn('  ⚠️  Mode correction activé');
        }
        $this->info('═══════════════════════════════════════════════════════');

        // ── Lignes de factures sans facture parente ────────────────────────────
        $this->orphan('invoice_items', 'invoice_id', 'invoices', 'id',
            'Lignes de factures sans facture parente');

        // ── Lignes de factures fournisseur sans facture ────────────────────────
        $this->orphan('supplier_invoice_items', 'supplier_invoice_id', 'supplier_invoices', 'id',
            'Lignes de factures fournisseur sans facture parente');

        // ── Allocations de paiement client sans facture ────────────────────────
        $this->orphan('client_payment_allocations', 'invoice_id', 'invoices', 'id',
            'Allocations de paiement client sans facture');

        // ── Allocations de paiement client sans paiement ──────────────────────
        $this->orphan('client_payment_allocations', 'client_payment_id', 'client_payments', 'id',
            'Allocations de paiement client sans encaissement');

        // ── Allocations de paiement fournisseur sans facture ──────────────────
        $this->orphan('supplier_payment_allocations', 'supplier_invoice_id', 'supplier_invoices', 'id',
            'Allocations de paiement fournisseur sans facture');

        // ── Lignes d'écriture sans écriture parente ───────────────────────────
        $this->orphan('journal_entry_lines', 'journal_entry_id', 'journal_entries', 'id',
            'Lignes d\'écriture comptable sans écriture parente');

        // ── Lignes de BL sans BL parent ───────────────────────────────────────
        $this->orphan('delivery_note_items', 'delivery_note_id', 'delivery_notes', 'id',
            'Lignes de BL sans BL parent');

        // ── Lignes de réception sans réception ────────────────────────────────
        $this->orphan('reception_items', 'reception_id', 'receptions', 'id',
            'Lignes de réception sans réception parente');

        // ── Lignes de commande sans commande ──────────────────────────────────
        $this->orphan('order_items', 'order_id', 'orders', 'id',
            'Lignes de commande sans commande parente');

        // ── Lignes de devis sans devis ────────────────────────────────────────
        $this->orphan('quote_items', 'quote_id', 'quotes', 'id',
            'Lignes de devis sans devis parent');

        // ── Bulletins de paie sans run de paie ────────────────────────────────
        $this->orphan('payroll_items', 'payroll_run_id', 'payroll_runs', 'id',
            'Bulletins de paie sans run de paie');

        // ── Rapport ────────────────────────────────────────────────────────────
        $this->newLine();
        $this->info('─────────────────────────────────────────────────────');
        if ($this->found === 0) {
            $this->info('  ✅  Aucun orphelin détecté');
        } else {
            $msg = $this->apply
                ? "  🔧  {$this->found} orphelin(s) détecté(s), {$this->fixed} supprimé(s)"
                : "  ⚠️   {$this->found} orphelin(s) détecté(s) — relancez avec --apply pour les supprimer";
            $this->warn($msg);
        }
        $this->info('═══════════════════════════════════════════════════════');

        return $this->found > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function orphan(
        string $table, string $fk, string $parentTable, string $parentPk, string $label
    ): void {
        $count = DB::table($table)
            ->whereNotNull($fk)
            ->whereNotExists(fn($q) =>
                $q->from($parentTable)->whereColumn("{$parentTable}.{$parentPk}", "{$table}.{$fk}")
            )
            ->count();

        if ($count === 0) {
            $this->line("  <fg=green>✓</> {$label}");
            return;
        }

        $this->found += $count;
        $this->line("  <fg=red>✗</> {$label} : <fg=red;options=bold>{$count}</>");

        if ($this->apply) {
            DB::table($table)
                ->whereNotNull($fk)
                ->whereNotExists(fn($q) =>
                    $q->from($parentTable)->whereColumn("{$parentTable}.{$parentPk}", "{$table}.{$fk}")
                )
                ->delete();
            $this->fixed += $count;
            $this->line("    <fg=yellow>→ {$count} enregistrement(s) supprimé(s) de {$table}</>");
        }
    }
}
