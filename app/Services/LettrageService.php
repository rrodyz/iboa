<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntryLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [COMPTA] Service de lettrage des comptes de tiers.
 *
 * Le lettrage consiste à rapprocher les écritures débitrices et créditrices
 * d'un même compte (411 clients, 401 fournisseurs…) qui se compensent.
 * Chaque groupe de lignes lettrées reçoit une même lettre (A, B, C… AA…).
 *
 * Règles SYSCOHADA :
 *  - La somme des débits lettrés = somme des crédits lettrés (équilibre obligatoire)
 *  - Seules les écritures VALIDÉES peuvent être lettrées
 *  - Le délettrage est toujours possible (pas de clôture du lettrage)
 */
class LettrageService
{
    // ─── Lettrage ─────────────────────────────────────────────────────────────

    /**
     * Lettre un ensemble de lignes d'écritures appartenant au même compte.
     *
     * @param  int[]  $lineIds   IDs des journal_entry_lines à lettrer
     * @param  int    $companyId
     * @return string La lettre attribuée
     * @throws \RuntimeException si les montants ne s'équilibrent pas
     */
    public function lettre(array $lineIds, int $companyId): string
    {
        if (count($lineIds) < 2) {
            throw new \RuntimeException('Il faut au moins 2 lignes pour lettrer.');
        }

        return DB::transaction(function () use ($lineIds, $companyId) {

            $lines = JournalEntryLine::with('journalEntry')
                ->whereIn('id', $lineIds)
                ->whereHas('journalEntry', fn($q) => $q
                    ->where('company_id', $companyId)
                    ->where('status', 'valide')
                )
                ->lockForUpdate()
                ->get();

            if ($lines->count() !== count($lineIds)) {
                throw new \RuntimeException(
                    'Certaines lignes sont introuvables ou appartiennent à des écritures non validées.'
                );
            }

            // Vérifier même compte
            $accountIds = $lines->pluck('account_id')->unique();
            if ($accountIds->count() > 1) {
                throw new \RuntimeException(
                    'Toutes les lignes doivent appartenir au même compte comptable.'
                );
            }

            // Vérifier qu'aucune n'est déjà lettrée
            $alreadyLettered = $lines->filter(fn($l) => $l->isLettered());
            if ($alreadyLettered->isNotEmpty()) {
                throw new \RuntimeException(
                    'Certaines lignes sont déjà lettrées (lettre: ' .
                    $alreadyLettered->pluck('reconciliation_ref')->unique()->implode(', ') .
                    '). Délettrez-les d\'abord.'
                );
            }

            // Vérifier équilibre débit = crédit
            $totalDebit  = $lines->sum('debit');
            $totalCredit = $lines->sum('credit');
            if ($totalDebit !== $totalCredit) {
                throw new \RuntimeException(
                    sprintf(
                        'Déséquilibre : Débit %s ≠ Crédit %s (écart : %s FCFA). ' .
                        'Le lettrage nécessite une compensation exacte.',
                        number_format($totalDebit, 0, ',', ' '),
                        number_format($totalCredit, 0, ',', ' '),
                        number_format(abs($totalDebit - $totalCredit), 0, ',', ' ')
                    )
                );
            }

            // Générer la prochaine lettre disponible pour ce compte
            $accountId = $accountIds->first();
            $lettre    = $this->nextLetter($accountId, $companyId);
            $now       = now();
            $userId    = Auth::id();

            JournalEntryLine::whereIn('id', $lineIds)->update([
                'reconciliation_ref' => $lettre,
                'lettered_at'        => $now,
                'lettered_by'        => $userId,
            ]);

            return $lettre;
        });
    }

    /**
     * Supprime le lettrage d'un groupe de lignes (même lettre).
     *
     * @param  string $lettre    La lettre à supprimer
     * @param  int    $accountId
     * @param  int    $companyId
     * @return int    Nombre de lignes délettrées
     */
    public function delettre(string $lettre, int $accountId, int $companyId): int
    {
        return DB::transaction(function () use ($lettre, $accountId, $companyId) {

            return JournalEntryLine::where('account_id', $accountId)
                ->where('reconciliation_ref', $lettre)
                ->whereHas('journalEntry', fn($q) => $q->where('company_id', $companyId))
                ->update([
                    'reconciliation_ref' => null,
                    'lettered_at'        => null,
                    'lettered_by'        => null,
                ]);
        });
    }

    // ─── Auto-lettrage ────────────────────────────────────────────────────────

    /**
     * Lettrage automatique : apparie les lignes de même montant et de sens opposé.
     *
     * Algorithme : pour chaque ligne débitrice non lettrée, cherche une ligne
     * créditrice non lettrée de même montant dans le même compte.
     *
     * @return array{matched: int, letters: string[]}
     */
    public function autoLettre(int $accountId, int $companyId): array
    {
        return DB::transaction(function () use ($accountId, $companyId) {

            $lines = JournalEntryLine::with('journalEntry')
                ->where('account_id', $accountId)
                ->whereNull('reconciliation_ref')
                ->whereHas('journalEntry', fn($q) => $q
                    ->where('company_id', $companyId)
                    ->where('status', 'valide')
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $debits  = $lines->where('debit', '>', 0)->values();
            $credits = $lines->where('credit', '>', 0)->values();

            $matched = 0;
            $letters = [];
            $usedCreditIdx = [];

            foreach ($debits as $dLine) {
                foreach ($credits as $idx => $cLine) {
                    if (in_array($idx, $usedCreditIdx)) {
                        continue;
                    }
                    if ($dLine->debit === $cLine->credit) {
                        $lettre = $this->nextLetter($accountId, $companyId);
                        $now    = now();
                        $userId = Auth::id();

                        JournalEntryLine::whereIn('id', [$dLine->id, $cLine->id])->update([
                            'reconciliation_ref' => $lettre,
                            'lettered_at'        => $now,
                            'lettered_by'        => $userId,
                        ]);

                        $usedCreditIdx[] = $idx;
                        $matched        += 2;
                        $letters[]       = $lettre;
                        break;
                    }
                }
            }

            return ['matched' => $matched, 'letters' => $letters];
        });
    }

    // ─── Lecture ──────────────────────────────────────────────────────────────

    /**
     * Retourne les lignes d'un compte avec leur statut de lettrage.
     * Utilisé pour l'affichage de l'écran de lettrage.
     */
    public function getLinesForAccount(
        Account $account,
        int $companyId,
        ?string $dateFrom = null,
        ?string $dateTo   = null
    ): array {
        $query = JournalEntryLine::with(['journalEntry.journalType', 'letteredBy'])
            ->where('account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($companyId, $dateFrom, $dateTo) {
                $q->where('company_id', $companyId)
                  ->where('status', 'valide');
                if ($dateFrom) $q->where('entry_date', '>=', $dateFrom);
                if ($dateTo)   $q->where('entry_date', '<=', $dateTo);
            })
            ->orderBy('created_at')
            ->orderBy('id');

        $lines = $query->get();

        // Grouper : lettrées (par lettre) + non lettrées
        $lettered   = $lines->whereNotNull('reconciliation_ref')
                             ->groupBy('reconciliation_ref')
                             ->sortKeys();
        $unlettered = $lines->whereNull('reconciliation_ref')->values();

        return compact('lettered', 'unlettered');
    }

    /**
     * Liste les comptes lettables (classe 4) avec leur solde non lettré.
     */
    public function getLettrablesAccounts(int $companyId): Collection
    {
        return Account::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_detail', true)
            ->whereHas('accountClass', fn($q) => $q->where('number', 4))
            ->withCount([
                'journalEntryLines as total_lines' => fn($q) => $q
                    ->whereHas('journalEntry', fn($q2) => $q2
                        ->where('company_id', $companyId)
                        ->where('status', 'valide')
                    ),
                'journalEntryLines as unlettered_lines' => fn($q) => $q
                    ->whereNull('reconciliation_ref')
                    ->whereHas('journalEntry', fn($q2) => $q2
                        ->where('company_id', $companyId)
                        ->where('status', 'valide')
                    ),
            ])
            ->having('total_lines', '>', 0)
            ->orderBy('code')
            ->get();
    }

    // ─── Helpers privés ───────────────────────────────────────────────────────

    /**
     * Génère la prochaine lettre de lettrage disponible pour un compte.
     * Séquence : A, B, …, Z, AA, AB, …, AZ, BA, …
     */
    private function nextLetter(int $accountId, int $companyId): string
    {
        $last = JournalEntryLine::where('account_id', $accountId)
            ->whereNotNull('reconciliation_ref')
            ->whereHas('journalEntry', fn($q) => $q->where('company_id', $companyId))
            ->orderByRaw('LENGTH(reconciliation_ref) DESC, reconciliation_ref DESC')
            ->value('reconciliation_ref');

        return $last ? $this->incrementLetter($last) : 'A';
    }

    /**
     * Incrémente une lettre comptable.
     * A → B → … → Z → AA → AB → … → AZ → BA → …
     */
    private function incrementLetter(string $letter): string
    {
        $len  = strlen($letter);
        $arr  = str_split($letter);
        $i    = $len - 1;

        while ($i >= 0) {
            if ($arr[$i] < 'Z') {
                $arr[$i] = chr(ord($arr[$i]) + 1);
                return implode('', $arr);
            }
            $arr[$i] = 'A';
            $i--;
        }

        // Débordement : AA, AAA…
        return str_repeat('A', $len + 1);
    }
}
