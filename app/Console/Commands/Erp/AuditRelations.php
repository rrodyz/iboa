<?php

namespace App\Console\Commands\Erp;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan erp:audit-relations
 *
 * Vérifie toutes les relations métier critiques de l'ERP et liste les anomalies.
 * Lecture seule — ne modifie aucune donnée.
 */
class AuditRelations extends Command
{
    protected $signature   = 'erp:audit-relations {--json : Sortie JSON}';
    protected $description = 'Audit des relations métier ERP : factures orphelines, documents sans écriture GL, etc.';

    private array $issues = [];
    private int   $ok     = 0;

    public function handle(): int
    {
        $this->info('');
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  ERP — Audit des relations métier');
        $this->info('═══════════════════════════════════════════════════════');

        // ── 1. Ventes ──────────────────────────────────────────────────────────
        $this->section('Ventes (Devis → Commande → BL → Facture → Encaissement)');
        $this->check('Factures sans client',
            DB::table('invoices')->whereNull('client_id')->whereNull('deleted_at')->count());
        $this->check('Factures validées sans écriture comptable',
            DB::table('invoices')
                ->whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'payee', 'en_retard'])
                ->whereNull('journal_entry_id')
                ->whereNull('deleted_at')
                ->count());
        $this->check('Lignes de facture sans facture parente',
            DB::table('invoice_items')
                ->whereNotExists(fn($q) => $q->from('invoices')->whereColumn('invoices.id', 'invoice_items.invoice_id'))
                ->count());
        $this->check('Encaissements sans client',
            DB::table('client_payments')->whereNull('client_id')->whereNull('deleted_at')->count());
        $this->check('Encaissements sans écriture comptable',
            DB::table('client_payments')
                ->whereNull('journal_entry_id')
                ->whereNull('deleted_at')
                ->count());
        $this->check('Allocations de paiement pointant une facture inexistante',
            DB::table('client_payment_allocations')
                ->whereNotExists(fn($q) => $q->from('invoices')->whereColumn('invoices.id', 'client_payment_allocations.invoice_id'))
                ->count());
        $this->check('Avoirs sans facture source',
            DB::table('credit_notes')
                ->whereNull('invoice_id')
                ->whereNull('deleted_at')
                ->count());

        // ── 2. Achats ──────────────────────────────────────────────────────────
        $this->section('Achats (DA → Commande → Réception → Facture → Paiement)');
        $this->check('Factures fournisseur sans fournisseur',
            DB::table('supplier_invoices')->whereNull('supplier_id')->whereNull('deleted_at')->count());
        $this->check('Factures fournisseur validées sans écriture comptable',
            DB::table('supplier_invoices')
                ->whereIn('status', ['validee', 'partiellement_payee', 'payee'])
                ->whereNull('journal_entry_id')
                ->whereNull('deleted_at')
                ->count());
        $this->check('Paiements fournisseurs sans fournisseur',
            DB::table('supplier_payments')->whereNull('supplier_id')->whereNull('deleted_at')->count());
        $this->check('Paiements fournisseurs sans écriture comptable',
            DB::table('supplier_payments')
                ->whereNull('journal_entry_id')
                ->whereNull('deleted_at')
                ->count());
        $this->check('Réceptions sans commande fournisseur',
            DB::table('receptions')
                ->whereNull('purchase_order_id')
                ->whereNull('deleted_at')
                ->count());

        // ── 3. Stock ───────────────────────────────────────────────────────────
        $this->section('Stock (Article → Mouvements → Valorisation)');
        $this->check('Mouvements de stock sans article',
            DB::table('stock_movements')->whereNull('product_id')->count());
        $this->check('Mouvements de stock sans entrepôt',
            DB::table('stock_movements')->whereNull('warehouse_id')->count());
        $this->check('Niveaux de stock négatifs',
            DB::table('product_stocks')->where('quantity', '<', 0)->count());
        $this->check('Niveaux de stock réservés > stock disponible',
            DB::table('product_stocks')
                ->whereColumn('reserved_quantity', '>', 'quantity')
                ->count());

        // ── 4. Comptabilité ────────────────────────────────────────────────────
        $this->section('Comptabilité (Écritures équilibrées)');
        $this->check('Écritures comptables déséquilibrées (débit ≠ crédit)',
            DB::table('journal_entries')
                ->whereColumn('total_debit', '!=', 'total_credit')
                ->where('status', '!=', 'brouillon')
                ->count());
        $this->check('Écritures validées sans lignes',
            DB::table('journal_entries')
                ->where('status', 'valide')
                ->whereNotExists(fn($q) => $q->from('journal_entry_lines')
                    ->whereColumn('journal_entry_lines.journal_entry_id', 'journal_entries.id'))
                ->count());
        $this->check('Lignes d\'écriture sans compte comptable',
            DB::table('journal_entry_lines')->whereNull('account_id')->count());
        $this->check('Lignes d\'écriture sans écriture parente',
            DB::table('journal_entry_lines')
                ->whereNotExists(fn($q) => $q->from('journal_entries')
                    ->whereColumn('journal_entries.id', 'journal_entry_lines.journal_entry_id'))
                ->count());

        // ── 5. RH / Paie ───────────────────────────────────────────────────────
        $this->section('RH / Paie (Employé → Contrat → Run → Comptabilité)');
        $this->check('Bulletins de paie sans employé',
            DB::table('payroll_items')->whereNull('employee_id')->count());
        $this->check('Runs de paie validés sans écriture comptable',
            DB::table('payroll_runs')
                ->whereIn('status', ['valide', 'paye'])
                ->whereNull('journal_entry_id')
                ->count());
        $this->check('Contrats sans employé',
            DB::table('employee_contracts')
                ->whereNull('employee_id')
                ->count());
        $this->check('Rubriques de paie sans compte comptable (risque GL)',
            DB::table('pay_rubrics')
                ->whereNull('account_code')
                ->where('is_active', true)
                ->count());

        // ── 6. Documents ───────────────────────────────────────────────────────
        $this->section('Numérotation & Paramétrage');
        $this->check('Factures sans numéro',
            DB::table('invoices')->whereNull('number')->whereNull('deleted_at')->count());
        $this->check('Commandes clients sans numéro',
            DB::table('orders')->whereNull('number')->whereNull('deleted_at')->count());
        $this->check('Commandes fournisseurs sans numéro',
            DB::table('purchase_orders')->whereNull('number')->whereNull('deleted_at')->count());

        // ── Rapport final ──────────────────────────────────────────────────────
        $this->newLine();
        $total = count($this->issues);

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok'     => $this->ok,
                'issues' => $this->issues,
                'total'  => $total,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $total > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info('─────────────────────────────────────────────────────');
        if ($total === 0) {
            $this->info("  ✅  Aucune anomalie détectée ({$this->ok} vérifications passées)");
        } else {
            $this->warn("  ⚠️   {$total} anomalie(s) détectée(s) / {$this->ok} vérification(s) OK");
            $this->newLine();
            $this->table(['Module', 'Anomalie', 'Nombre'], $this->issues);
        }
        $this->info('═══════════════════════════════════════════════════════');

        return $total > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private string $currentSection = '';

    private function section(string $label): void
    {
        $this->currentSection = $label;
        if (! $this->option('json')) {
            $this->newLine();
            $this->line("  <fg=cyan>▸ {$label}</>");
        }
    }

    private function check(string $label, int $count): void
    {
        if ($count === 0) {
            $this->ok++;
            if (! $this->option('json')) {
                $this->line("    <fg=green>✓</> {$label}");
            }
        } else {
            $this->issues[] = [$this->currentSection, $label, $count];
            if (! $this->option('json')) {
                $this->line("    <fg=red>✗</> {$label} : <fg=red;options=bold>{$count}</>");
            }
        }
    }
}
