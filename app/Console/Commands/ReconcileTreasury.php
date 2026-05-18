<?php

namespace App\Console\Commands;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\ClientPayment;
use App\Models\SupplierPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Détecte les incohérences entre les paiements (ClientPayment / SupplierPayment)
 * et les transactions caisse (CashTransaction). Lecture seule.
 *
 * Cas détectés :
 *  1. Paiement avec cash_account_id mais SANS cash_transaction (sync manquée)
 *  2. CashAccount dont current_balance ne matche pas Σ(opening + entrées - sorties)
 *  3. Comparaison compta (compte 5xx) ↔ trésorerie (CashAccount)
 *
 * Usage :
 *   php artisan treasury:reconcile           → résumé
 *   php artisan treasury:reconcile -v        → détail par anomalie
 */
class ReconcileTreasury extends Command
{
    protected $signature   = 'treasury:reconcile {--show-all : Détail complet de toutes les anomalies}
                              {--fix-balances : Recalcule current_balance et balance_after de toutes les caisses}';
    protected $description = 'Audit la cohérence trésorerie (paiements ↔ transactions caisse ↔ comptabilité)';

    public function handle(): int
    {
        if ($this->option('fix-balances')) {
            return $this->fixBalances();
        }

        $totalAnomalies = 0;

        $this->info("═══ Audit trésorerie ═══\n");

        // ── 1. SupplierPayments sans cash_transaction ────────────────────────
        $orphanSupplier = SupplierPayment::whereNotNull('cash_account_id')->get()
            ->filter(function ($p) {
                return CashTransaction::where(function ($q) use ($p) {
                    $q->where(function ($q2) use ($p) {
                        $q2->where('reference_type', 'App\\Models\\SupplierPayment')
                           ->where('reference_id', $p->id);
                    })->orWhere(function ($q2) use ($p) {
                        $q2->where('reference_type', 'SupplierPayment')
                           ->where('reference_id', $p->id);
                    });
                })->doesntExist();
            });

        $this->line("▸ <fg=yellow>Décaissements (SupplierPayment) sans transaction caisse</fg=yellow>");
        if ($orphanSupplier->isEmpty()) {
            $this->line("  ✓ Aucun");
        } else {
            $totalAnomalies += $orphanSupplier->count();
            $this->line("  ✗ <fg=red>{$orphanSupplier->count()} anomalie(s)</fg=red>");
            foreach ($orphanSupplier as $p) {
                $this->line(sprintf("    %s | %s | %s FCFA | caisse: %s",
                    $p->number,
                    $p->payment_date?->format('d/m/Y'),
                    number_format($p->amount, 0, ',', ' '),
                    $p->cashAccount?->name ?? '?'
                ));
            }
        }

        // ── 2. ClientPayments sans cash_transaction ──────────────────────────
        $orphanClient = ClientPayment::whereNotNull('cash_account_id')->get()
            ->filter(function ($p) {
                return CashTransaction::where('reference_type', 'App\\Models\\ClientPayment')
                    ->where('reference_id', $p->id)
                    ->doesntExist()
                    && CashTransaction::where('reference_type', 'ClientPayment')
                    ->where('reference_id', $p->id)
                    ->doesntExist();
            });

        $this->line("\n▸ <fg=yellow>Encaissements (ClientPayment) sans transaction caisse</fg=yellow>");
        if ($orphanClient->isEmpty()) {
            $this->line("  ✓ Aucun");
        } else {
            $totalAnomalies += $orphanClient->count();
            $this->line("  ✗ <fg=red>{$orphanClient->count()} anomalie(s)</fg=red>");
            foreach ($orphanClient as $p) {
                $this->line(sprintf("    %s | %s | %s FCFA | caisse: %s",
                    $p->number,
                    $p->payment_date?->format('d/m/Y'),
                    number_format($p->amount, 0, ',', ' '),
                    $p->cashAccount?->name ?? '?'
                ));
            }
        }

        // ── 3. CashAccount avec current_balance incohérent ────────────────────
        $this->line("\n▸ <fg=yellow>Soldes CashAccount vs. somme transactions</fg=yellow>");
        $cashIssues = 0;
        foreach (CashAccount::all() as $ca) {
            $credits = CashTransaction::where('cash_account_id', $ca->id)->where('type', 'credit')->sum('amount');
            $debits  = CashTransaction::where('cash_account_id', $ca->id)->where('type', 'debit')->sum('amount');
            $calc    = (int) $ca->opening_balance + (int) $credits - (int) $debits;
            $diff    = (int) $ca->current_balance - $calc;

            if (abs($diff) >= 1) {
                $cashIssues++;
                $this->line(sprintf("    %s (%s) : solde=%s, calc=%s, écart=<fg=red>%s</fg=red>",
                    $ca->name, $ca->code,
                    number_format($ca->current_balance, 0, ',', ' '),
                    number_format($calc, 0, ',', ' '),
                    number_format($diff, 0, ',', ' ')
                ));
            } elseif ($this->option('show-all')) {
                $this->line(sprintf("    %s (%s) : ✓ %s FCFA",
                    $ca->name, $ca->code, number_format($calc, 0, ',', ' ')
                ));
            }
        }
        if ($cashIssues === 0) {
            $this->line("  ✓ Tous les soldes sont cohérents");
        } else {
            $totalAnomalies += $cashIssues;
        }

        // ── 4. Soldes négatifs détectés ──────────────────────────────────────
        $this->line("\n▸ <fg=yellow>Caisses au solde négatif (impossible physiquement)</fg=yellow>");
        $negatives = CashAccount::where('current_balance', '<', 0)->get();
        if ($negatives->isEmpty()) {
            $this->line("  ✓ Aucune");
        } else {
            $totalAnomalies += $negatives->count();
            foreach ($negatives as $ca) {
                $this->line(sprintf("    %s (%s) : <fg=red>%s FCFA</fg=red>",
                    $ca->name, $ca->code,
                    number_format($ca->current_balance, 0, ',', ' ')
                ));
            }
        }

        // ── 5. Comparaison compta 5xx ↔ trésorerie ───────────────────────────
        $this->line("\n▸ <fg=yellow>Comparaison comptabilité ↔ trésorerie</fg=yellow>");
        // Total caisses physiques
        $physicalTotal = CashAccount::sum('current_balance');
        // Solde compta des comptes 5xx (caisse + banque + mobile)
        $compta5xx = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->where('journal_entries.status', 'valide')
            ->where('accounts.code', 'like', '5%')
            ->selectRaw('SUM(debit) - SUM(credit) as solde')
            ->value('solde');

        $diff5 = (int) $physicalTotal - (int) $compta5xx;
        $verdict = abs($diff5) < 1 ? '✓ COHÉRENT' : '⚠ ÉCART';
        $this->line(sprintf("  Trésorerie physique : %s FCFA", number_format($physicalTotal, 0, ',', ' ')));
        $this->line(sprintf("  Compta classe 5    : %s FCFA", number_format($compta5xx, 0, ',', ' ')));
        $this->line(sprintf("  Écart              : %s FCFA  %s",
            number_format($diff5, 0, ',', ' '),
            abs($diff5) < 1 ? '<fg=green>' . $verdict . '</fg=green>' : '<fg=red>' . $verdict . '</fg=red>'
        ));

        if (abs($diff5) >= 1) {
            $totalAnomalies++;
        }

        // ── Bilan ────────────────────────────────────────────────────────────
        $this->newLine();
        if ($totalAnomalies === 0) {
            $this->info("✅ Trésorerie 100% cohérente — aucune anomalie détectée.");
            return self::SUCCESS;
        }
        $this->warn("⚠  {$totalAnomalies} anomalie(s) détectée(s). Inspection manuelle recommandée.");
        $this->line("\nActions possibles selon le cas :");
        $this->line("  • Paiement orphelin avec cash_account → recréer la cash_transaction manuellement");
        $this->line("  • Solde CashAccount incohérent → <fg=cyan>php artisan treasury:reconcile --fix-balances</fg=cyan>");
        $this->line("  • Caisse négative → annuler ou rectifier le mouvement fautif");
        $this->line("  • Écart compta/tréso → audit ciblé par compte (commande à venir)");

        return self::FAILURE;
    }

    /**
     * Recalcule current_balance et balance_after de toutes les caisses.
     * Pour chaque CashAccount :
     *   1. Reset current_balance = opening_balance
     *   2. Parcourt ses transactions dans l'ordre chronologique
     *   3. Met à jour balance_after de chaque transaction
     *   4. Définit current_balance final
     *
     * Si une transaction ferait passer le solde en négatif → la signale (mais
     * n'interrompt pas, contrairement au runtime). À l'utilisateur de décider.
     */
    private function fixBalances(): int
    {
        $this->info("═══ Reconstitution des soldes caisse ═══\n");

        if (!$this->confirm('Cette opération va modifier `current_balance` et `balance_after` sur les CashAccount et CashTransactions. Continuer ?', true)) {
            $this->line('Annulé.');
            return self::SUCCESS;
        }

        $accounts = CashAccount::all();
        $fixed = 0;
        $warnings = 0;

        DB::transaction(function () use ($accounts, &$fixed, &$warnings) {
            foreach ($accounts as $ca) {
                $running = (int) $ca->opening_balance;
                $hasNegative = false;

                $txs = CashTransaction::where('cash_account_id', $ca->id)
                    ->orderBy('transaction_date')
                    ->orderBy('id')
                    ->get();

                foreach ($txs as $tx) {
                    $amount = (int) $tx->amount;
                    $running += ($tx->type === 'credit' ? $amount : -$amount);
                    if ($running < 0) $hasNegative = true;

                    if ((int) $tx->balance_after !== $running) {
                        $tx->update(['balance_after' => $running]);
                    }
                }

                if ((int) $ca->current_balance !== $running) {
                    $ca->update(['current_balance' => $running]);
                    $this->line(sprintf('  ✓ %s : %s → %s FCFA',
                        $ca->name,
                        number_format((int) $ca->getOriginal('current_balance'), 0, ',', ' '),
                        number_format($running, 0, ',', ' ')
                    ));
                    $fixed++;
                }

                if ($hasNegative) {
                    $this->warn("  ⚠ {$ca->name} : a eu un solde négatif à un moment de l'historique");
                    $warnings++;
                }
            }
        });

        $this->newLine();
        $this->info("✅ {$fixed} caisse(s) corrigée(s). " . ($warnings > 0 ? "{$warnings} warning(s) à inspecter." : ""));
        $this->line("\nRelancez `php artisan treasury:reconcile` pour vérifier.");
        return self::SUCCESS;
    }
}
