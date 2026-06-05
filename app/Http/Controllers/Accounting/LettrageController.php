<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntryLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LettrageController extends Controller
{
    /**
     * Main view — pick a tiers account (class 4) and see its unlettered lines.
     */
    public function index(Request $request): View
    {
        $companyId = auth()->user()->company_id;

        // All class 4 detail tiers accounts — pas de filtre is_active : un tiers
        // désactivé peut conserver des lignes non lettrées à rapprocher.
        $accounts = Account::where('company_id', $companyId)
            ->where('code', 'like', '4%')
            ->where('is_detail', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $selectedAccount = null;
        $lines           = collect();
        $letteredGroups  = collect();
        $stats           = null;
        $exactPairs      = [];   // [line_id => paired_line_id]

        if ($accountId = $request->account_id) {
            $selectedAccount = Account::findOrFail($accountId);

            // Unlettered lines
            $lines = JournalEntryLine::with(['journalEntry.journalType'])
                ->where('account_id', $accountId)
                ->whereNull('reconciliation_ref')
                ->whereHas('journalEntry', fn ($q) => $q
                    ->where('company_id', $companyId)
                    ->where('status', 'valide'))
                ->orderBy('journal_entry_id')
                ->orderBy('id')
                ->get();

            // Already lettered lines — group by reconciliation_ref
            $letteredGroups = JournalEntryLine::with(['journalEntry', 'letteredBy'])
                ->where('account_id', $accountId)
                ->whereNotNull('reconciliation_ref')
                ->whereHas('journalEntry', fn ($q) => $q
                    ->where('company_id', $companyId)
                    ->where('status', 'valide'))
                ->orderBy('reconciliation_ref')
                ->orderBy('id')
                ->get()
                ->groupBy('reconciliation_ref');

            // ── Stats ────────────────────────────────────────────────────────
            $allCount         = $lines->count() + $letteredGroups->flatten()->count();
            $letteredCount    = $letteredGroups->flatten()->count();
            $unletteredDebit  = $lines->sum('debit');
            $unletteredCredit = $lines->sum('credit');

            $stats = [
                'total'            => $allCount,
                'lettered'         => $letteredCount,
                'unlettered'       => $lines->count(),
                'pct_lettered'     => $allCount > 0 ? round($letteredCount / $allCount * 100) : 0,
                'unlettered_debit' => $unletteredDebit,
                'unlettered_credit'=> $unletteredCredit,
                'solde_residuel'   => $unletteredDebit - $unletteredCredit,
                'groups_count'     => $letteredGroups->count(),
            ];

            // ── Détection des paires exactes 1↔1 (pour surlignage UI) ───────
            $debits  = $lines->filter(fn ($l) => (float) $l->debit  > 0)->values();
            $credits = $lines->filter(fn ($l) => (float) $l->credit > 0)->values();
            $usedD   = [];
            $usedC   = [];

            foreach ($debits as $d) {
                if (in_array($d->id, $usedD, true)) continue;
                $amount = (int) round((float) $d->debit);
                $match  = $credits->first(function ($c) use ($amount, $usedC) {
                    return ! in_array($c->id, $usedC, true)
                        && (int) round((float) $c->credit) === $amount;
                });
                if ($match) {
                    $exactPairs[$d->id]     = $match->id;
                    $exactPairs[$match->id] = $d->id;
                    $usedD[] = $d->id;
                    $usedC[] = $match->id;
                }
            }
        }

        return view('comptabilite.lettrage.index', compact(
            'accounts', 'selectedAccount', 'lines', 'letteredGroups', 'stats', 'exactPairs'
        ));
    }

    /**
     * AJAX: apply lettrage to a set of line IDs.
     * The lines must balance (sum debit = sum credit).
     */
    public function apply(Request $request): JsonResponse
    {
        $request->validate([
            'line_ids'   => ['required', 'array', 'min:2'],
            'line_ids.*' => ['integer', 'exists:journal_entry_lines,id'],
        ]);

        $ids   = $request->line_ids;
        $lines = JournalEntryLine::whereIn('id', $ids)->get();

        // All must be same account
        if ($lines->pluck('account_id')->unique()->count() > 1) {
            return response()->json(['ok' => false, 'message' => 'Les lignes doivent appartenir au même compte.'], 422);
        }

        // Must balance
        $sumDebit  = $lines->sum('debit');
        $sumCredit = $lines->sum('credit');

        if ($sumDebit !== $sumCredit) {
            return response()->json(['ok' => false, 'message' => 'Le lettrage doit être équilibré (débit = crédit). Écart : ' . abs($sumDebit - $sumCredit) . ' FCFA.'], 422);
        }

        // None should already be lettered
        if ($lines->whereNotNull('reconciliation_ref')->count() > 0) {
            return response()->json(['ok' => false, 'message' => 'Certaines lignes sont déjà lettrées.'], 422);
        }

        $accountId = $lines->first()->account_id;
        $ref       = $this->nextLetter($accountId);
        $now       = now();
        $userId    = Auth::id();

        JournalEntryLine::whereIn('id', $ids)->update([
            'reconciliation_ref' => $ref,
            'lettered_at'        => $now,
            'lettered_by'        => $userId,
        ]);

        // [AUDIT-L] Trace l'opération
        app(\App\Services\AuditService::class)->log(
            'lettrage_applied',
            null,
            [],
            ['ref' => $ref, 'lines' => $ids, 'amount' => $sumDebit]
        );

        return response()->json(['ok' => true, 'ref' => $ref, 'message' => 'Lettrage appliqué : lettre ' . $ref]);
    }

    /**
     * AJAX: remove lettrage from a set of lines sharing the same ref.
     */
    public function remove(Request $request): JsonResponse
    {
        $request->validate([
            'ref' => ['required', 'string'],
        ]);

        // [AUDIT-L] Snapshot avant suppression pour audit
        $linesAffected = JournalEntryLine::where('reconciliation_ref', $request->ref)
            ->pluck('id')->toArray();

        JournalEntryLine::where('reconciliation_ref', $request->ref)->update([
            'reconciliation_ref' => null,
            'lettered_at'        => null,
            'lettered_by'        => null,
        ]);

        // Audit trail
        app(\App\Services\AuditService::class)->log(
            'lettrage_removed',
            null,
            ['ref' => $request->ref, 'lines' => $linesAffected],
            []
        );

        return response()->json(['ok' => true, 'message' => 'Lettrage supprimé.']);
    }

    /**
     * [OPTION-C] AJAX: lettrage automatique sur un compte.
     *
     * Algorithme : pour chaque ligne non lettrée de débit, cherche LA ligne de crédit
     * non lettrée avec un montant strictement égal et non encore appariée. Lettre les
     * couples trouvés un par un avec une référence unique par couple.
     *
     * Le matching exact (montant identique) couvre 80% des cas pratiques :
     *   - Facture 100 000 FCFA / Encaissement 100 000 FCFA → lettré
     *   - Facture fournisseur / Décaissement → lettré
     *
     * Les cas complexes (avoir partiel, paiement à cheval sur 2 factures) restent
     * en saisie manuelle via apply().
     */
    public function autoApply(Request $request): JsonResponse
    {
        $request->validate([
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
        ]);

        $accountId = (int) $request->account_id;

        $matched = 0;
        $refs    = [];

        DB::transaction(function () use ($accountId, &$matched, &$refs) {
            // Charge toutes les lignes non lettrées du compte, écritures validées uniquement
            $lines = JournalEntryLine::where('account_id', $accountId)
                ->whereNull('reconciliation_ref')
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'valide'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            // Indexe par montant : pour le débit ET le crédit
            $debits  = $lines->filter(fn ($l) => (float) $l->debit  > 0)->values();
            $credits = $lines->filter(fn ($l) => (float) $l->credit > 0)->values();

            $usedDebitIds  = [];
            $usedCreditIds = [];

            // ── Phase 1 : matching 1↔1 (le plus fiable) ─────────────────────
            foreach ($debits as $debit) {
                if (in_array($debit->id, $usedDebitIds, true)) continue;

                $amount = (int) round((float) $debit->debit);

                $match = $credits->first(function ($c) use ($amount, $usedCreditIds) {
                    if (in_array($c->id, $usedCreditIds, true)) return false;
                    return (int) round((float) $c->credit) === $amount;
                });

                if (!$match) continue;

                $ref    = $this->nextLetter($accountId);
                $now    = now();
                $userId = Auth::id();
                JournalEntryLine::whereIn('id', [$debit->id, $match->id])
                    ->update(['reconciliation_ref' => $ref, 'lettered_at' => $now, 'lettered_by' => $userId]);

                $usedDebitIds[]  = $debit->id;
                $usedCreditIds[] = $match->id;
                $refs[]          = $ref;
                $matched++;
            }

            // ── Phase 2 : matching 1↔N (un crédit couvre plusieurs débits identiques) ─────
            // Cas typique : un encaissement client qui solde 2-3 factures.
            // On cherche les crédits restants dont le montant = somme de N débits non utilisés.
            $remainingCredits = $credits->whereNotIn('id', $usedCreditIds)->values();
            $remainingDebits  = $debits->whereNotIn('id', $usedDebitIds)->values();

            foreach ($remainingCredits as $credit) {
                $target = (int) round((float) $credit->credit);
                $combo  = $this->findCombination($remainingDebits, $target, $usedDebitIds);

                if (!$combo) continue;

                $ref    = $this->nextLetter($accountId);
                $now    = now();
                $userId = Auth::id();
                $ids    = array_merge([$credit->id], $combo);

                JournalEntryLine::whereIn('id', $ids)
                    ->update(['reconciliation_ref' => $ref, 'lettered_at' => $now, 'lettered_by' => $userId]);

                foreach ($combo as $debId) $usedDebitIds[] = $debId;
                $usedCreditIds[] = $credit->id;
                $refs[]          = $ref;
                $matched++;
            }

            // ── Phase 3 : matching N↔1 (plusieurs crédits couvrent un débit) ─────
            // Cas symétrique : un débit fournisseur soldé par plusieurs paiements
            $remainingCredits = $credits->whereNotIn('id', $usedCreditIds)->values();
            $remainingDebits  = $debits->whereNotIn('id', $usedDebitIds)->values();

            foreach ($remainingDebits as $debit) {
                $target = (int) round((float) $debit->debit);
                $combo  = $this->findCombination($remainingCredits, $target, $usedCreditIds, 'credit');

                if (!$combo) continue;

                $ref    = $this->nextLetter($accountId);
                $now    = now();
                $userId = Auth::id();
                $ids    = array_merge([$debit->id], $combo);

                JournalEntryLine::whereIn('id', $ids)
                    ->update(['reconciliation_ref' => $ref, 'lettered_at' => $now, 'lettered_by' => $userId]);

                foreach ($combo as $crId) $usedCreditIds[] = $crId;
                $usedDebitIds[] = $debit->id;
                $refs[]         = $ref;
                $matched++;
            }
        });

        // Audit
        app(\App\Services\AuditService::class)->log(
            'lettrage_auto_applied',
            null,
            [],
            ['account_id' => $accountId, 'matched_pairs' => $matched, 'refs' => $refs]
        );

        return response()->json([
            'ok'      => true,
            'matched' => $matched,
            'refs'    => $refs,
            'message' => $matched === 0
                ? 'Aucun lettrage automatique trouvé.'
                : "{$matched} groupe(s) lettré(s) automatiquement (1↔1, 1↔N et N↔1).",
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Génère la prochaine lettre séquentielle pour un compte.
     * A → B → … → Z → AA → AB → … → AZ → BA → …
     */
    private function nextLetter(int $accountId): string
    {
        $last = JournalEntryLine::where('account_id', $accountId)
            ->whereNotNull('reconciliation_ref')
            ->where('reconciliation_ref', 'regexp', '^[A-Z]+$')
            ->orderByRaw('LENGTH(reconciliation_ref) DESC, reconciliation_ref DESC')
            ->value('reconciliation_ref');

        if (!$last) return 'A';

        // Incrémenter : Z → AA, AZ → BA, ZZ → AAA
        $arr = str_split($last);
        $i   = count($arr) - 1;
        while ($i >= 0) {
            if ($arr[$i] < 'Z') {
                $arr[$i] = chr(ord($arr[$i]) + 1);
                return implode('', $arr);
            }
            $arr[$i] = 'A';
            $i--;
        }
        return str_repeat('A', strlen($last) + 1);
    }

    /**
     * Cherche une combinaison de lignes dont la somme du champ (debit ou credit)
     * vaut exactement le montant cible. Algo glouton avec backtracking limité à
     * 2-4 éléments (cas réalistes en compta). Retourne le tableau des IDs.
     *
     * Limites :
     *  - Combinaisons de 2 à 4 lignes (au-delà : trop ambigu, lettrage manuel requis)
     *  - Comparaison à l'unité entière (arrondi par round())
     *
     * @param  \Illuminate\Support\Collection  $candidates  Lignes candidates
     * @param  int                              $target     Montant cible
     * @param  array                            $excluded   IDs déjà utilisés
     * @param  string                           $field      'debit' ou 'credit'
     * @return array|null  IDs si trouvé, null sinon
     */
    private function findCombination($candidates, int $target, array $excluded, string $field = 'debit'): ?array
    {
        $available = $candidates->filter(fn ($c) => !in_array($c->id, $excluded, true))
            ->map(fn ($c) => ['id' => $c->id, 'amount' => (int) round((float) $c->{$field})])
            ->values()
            ->all();

        $n = count($available);
        if ($n < 2) return null;

        // Combinaisons de 2 lignes
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($available[$i]['amount'] + $available[$j]['amount'] === $target) {
                    return [$available[$i]['id'], $available[$j]['id']];
                }
            }
        }

        // Combinaisons de 3 lignes
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                for ($k = $j + 1; $k < $n; $k++) {
                    if ($available[$i]['amount'] + $available[$j]['amount'] + $available[$k]['amount'] === $target) {
                        return [$available[$i]['id'], $available[$j]['id'], $available[$k]['id']];
                    }
                }
            }
        }

        // Combinaisons de 4 lignes (cap pour éviter explosion combinatoire)
        if ($n <= 30) {  // au-delà de 30 candidats, on stoppe à 3
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    for ($k = $j + 1; $k < $n; $k++) {
                        for ($l = $k + 1; $l < $n; $l++) {
                            if ($available[$i]['amount'] + $available[$j]['amount'] + $available[$k]['amount'] + $available[$l]['amount'] === $target) {
                                return [$available[$i]['id'], $available[$j]['id'], $available[$k]['id'], $available[$l]['id']];
                            }
                        }
                    }
                }
            }
        }

        return null;
    }
}
