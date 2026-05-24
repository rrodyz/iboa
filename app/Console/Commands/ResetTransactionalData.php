<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [DANGER] Vide toutes les données transactionnelles de l'ERP.
 *
 * Préserve : utilisateurs, rôles/permissions, société, plan comptable, codes journaux,
 * unités, TVA, paiements terms, exercices, entrepôts, produits, clients, fournisseurs,
 * comptes de trésorerie, séquences documents.
 *
 * Vide : devis, commandes, BL, factures, avoirs, paiements, demandes/PO/réception/FF,
 * mouvements stock, écritures comptables, audit logs, etc.
 *
 * Resets : soldes comptables, balances tiers, balance caisse à opening, last_movement_at.
 *
 * Usage :
 *   php artisan db:reset-transactional --dry         # voir ce qui serait supprimé
 *   php artisan db:reset-transactional --force       # exécuter pour de bon
 */
class ResetTransactionalData extends Command
{
    protected $signature = 'db:reset-transactional
                            {--dry : Affiche seulement ce qui serait supprimé}
                            {--force : Exécute sans demander de confirmation interactive}';

    protected $description = '[DANGER] Vide toutes les données transactionnelles (préserve paramétrages + utilisateurs).';

    /**
     * Tables transactionnelles à vider — ordre des FK gérée via SET FOREIGN_KEY_CHECKS=0.
     * Ordre conservé du plus dépendant au plus parent pour lisibilité.
     */
    private array $tables = [
        // Documents annexes
        'attachments', 'audit_logs', 'document_sequence_audits',

        // Trésorerie / paiements (children first)
        'cash_transactions', 'bank_deposits',
        'client_payment_allocations', 'client_payments',
        'supplier_payment_allocations', 'supplier_payments',
        'payment_schedules',

        // Ventes
        'credit_note_items', 'credit_notes',
        'invoice_items', 'invoices',
        'delivery_note_items', 'delivery_notes',
        'order_items', 'orders',
        'quote_items', 'quotes',

        // Achats
        'supplier_return_items', 'supplier_returns',
        'supplier_invoice_items', 'supplier_invoices',
        'reception_items', 'receptions',
        'purchase_order_items', 'purchase_orders',
        'rfq_quote_items', 'rfq_quotes', 'rfq_suppliers', 'rfq_items', 'rfqs',
        'purchase_request_items', 'purchase_requests',

        // Stock
        'stock_transfer_items', 'stock_transfers',
        'inventory_session_items', 'inventory_sessions',
        'stock_movements', 'product_stocks', 'stock_lots',

        // Comptabilité
        'bank_reconciliation_lines', 'bank_reconciliations',
        'vat_declaration_items', 'vat_declarations',
        'accounting_period_locks',
        'journal_entry_lines', 'journal_entries',
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        $this->newLine();
        $this->warn('⚠️  ' . ($dry ? 'SIMULATION' : 'PURGE DES DONNÉES TRANSACTIONNELLES'));
        $this->newLine();

        // Inventaire
        $counts = [];
        $total = 0;
        foreach ($this->tables as $t) {
            if (Schema::hasTable($t)) {
                $counts[$t] = (int) DB::table($t)->count();
                $total += $counts[$t];
            }
        }

        $this->line('  Tables à vider :');
        foreach ($counts as $t => $c) {
            if ($c === 0) continue;
            $this->line(sprintf('    %-32s %d lignes', $t, $c));
        }
        $this->newLine();
        $this->info("  Total : $total lignes à supprimer.");
        $this->newLine();

        if ($dry) {
            $this->line('  <fg=gray>Mode --dry : aucune modification effectuée.</>');
            return self::SUCCESS;
        }

        if (!$this->option('force')) {
            if (!$this->confirm('Confirmer la purge ? Cette action est IRRÉVERSIBLE.', false)) {
                $this->line('Annulé.');
                return self::SUCCESS;
            }
        }

        $this->purge();
        $this->resetCounters();
        $this->info('  ✓ Purge terminée. ERP propre, paramétrages conservés.');
        return self::SUCCESS;
    }

    private function purge(): void
    {
        $this->line('  Purge en cours…');

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($this->tables as $t) {
                if (Schema::hasTable($t)) {
                    DB::table($t)->truncate();
                    $this->line("    ✓ $t vidée");
                }
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function resetCounters(): void
    {
        $this->newLine();
        $this->line('  Reset des compteurs sur tables préservées…');

        // [SEQ-RESET] Numérotation documents : last_number → 0 pour repartir à 001
        if (Schema::hasTable('document_sequences')) {
            $n = DB::table('document_sequences')->update(['last_number' => 0]);
            $this->line("    ✓ document_sequences : $n séquences last_number → 0 (prochaine doc = 001)");
        }

        // Soldes comptables
        if (Schema::hasTable('accounts')) {
            DB::table('accounts')->update(['debit_balance' => 0, 'credit_balance' => 0]);
            $this->line('    ✓ accounts : debit/credit balances → 0');
        }

        // Balance clients
        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'balance')) {
            DB::table('clients')->update(['balance' => 0]);
            $this->line('    ✓ clients : balance → 0');
        }

        // Balance fournisseurs
        if (Schema::hasTable('suppliers') && Schema::hasColumn('suppliers', 'balance')) {
            DB::table('suppliers')->update(['balance' => 0]);
            $this->line('    ✓ suppliers : balance → 0');
        }

        // Caisse/banque : current_balance ← opening_balance
        if (Schema::hasTable('cash_accounts')) {
            DB::statement('UPDATE cash_accounts SET current_balance = opening_balance');
            $this->line('    ✓ cash_accounts : current_balance ← opening_balance');
        }

        // Produits : derniers prix d'achat, CMP, etc. — laissés tels quels (référentiel)
        // si vous voulez tout reset : décommentez
        // if (Schema::hasTable('products')) {
        //     DB::table('products')->update(['weighted_avg_cost' => 0, 'last_purchase_price' => 0]);
        //     $this->line('    ✓ products : weighted_avg_cost / last_purchase_price → 0');
        // }
    }
}
