<?php

namespace App\Services;

use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\CashAccount;
use App\Models\Company;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BankReconciliationService
{
    public function __construct(private DocumentSequenceService $sequenceService) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Create
    // ─────────────────────────────────────────────────────────────────────────
    public function create(array $data): BankReconciliation
    {
        return DB::transaction(function () use ($data) {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            $company             = Company::findOrFail(Auth::user()->company_id);
            $data['company_id']  = $company->id;
            $data['number']      = $this->sequenceService->nextNumber($company, 'rapprochement');
            $data['created_by']  = Auth::id();
            $data['status']      = 'brouillon';

            $rapprochement = BankReconciliation::create($data);

            foreach ($lines as $i => $line) {
                if (empty($line['label'])) continue;
                $rapprochement->lines()->create([
                    'value_date' => $line['value_date'] ?? $data['statement_date'],
                    'label'      => $line['label'],
                    'reference'  => $line['reference'] ?? null,
                    'debit'      => (int) ($line['debit']  ?? 0),
                    'credit'     => (int) ($line['credit'] ?? 0),
                    'is_matched' => false,
                    'sort_order' => $i,
                ]);
            }

            $this->recalculate($rapprochement);
            return $rapprochement->fresh();
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Match a bank line with a journal entry line
    // ─────────────────────────────────────────────────────────────────────────
    public function matchLine(BankStatementLine $bankLine, int $journalLineId): void
    {
        $journalLine = JournalEntryLine::findOrFail($journalLineId);

        DB::transaction(function () use ($bankLine, $journalLine) {
            $bankLine->update([
                'journal_entry_line_id' => $journalLine->id,
                'is_matched'            => true,
            ]);

            // [FIX-RAPPR-01] Only set reconciliation_ref if the line is not already lettered.
            // Lettrage refs start with 'LTR-'; overwriting them would silently break lettrage.
            // For bank reconciliation we store the ref in bank_statement_lines.journal_entry_line_id,
            // so we only set reconciliation_ref on the journal line when it has no prior lettrage.
            if (empty($journalLine->reconciliation_ref) || !str_starts_with($journalLine->reconciliation_ref, 'LTR-')) {
                $journalLine->update(['reconciliation_ref' => 'RAPPR-' . $bankLine->bank_reconciliation_id]);
            }
        });

        $this->recalculate($bankLine->reconciliation);
    }

    public function unmatchLine(BankStatementLine $bankLine): void
    {
        DB::transaction(function () use ($bankLine) {
            if ($bankLine->journal_entry_line_id) {
                // [FIX-RAPPR-02] Only clear reconciliation_ref if it is ours (RAPPR-).
                // Lettrage refs (LTR-) must not be touched here.
                $jel = JournalEntryLine::find($bankLine->journal_entry_line_id);
                if ($jel && str_starts_with((string) $jel->reconciliation_ref, 'RAPPR-')) {
                    $jel->update(['reconciliation_ref' => null]);
                }
            }
            $bankLine->update(['journal_entry_line_id' => null, 'is_matched' => false]);
        });

        $this->recalculate($bankLine->reconciliation);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Validate
    // ─────────────────────────────────────────────────────────────────────────
    public function validate(BankReconciliation $rec): BankReconciliation
    {
        return DB::transaction(function () use ($rec) {
            // Lock to prevent concurrent double-validation.
            $rec = BankReconciliation::lockForUpdate()->findOrFail($rec->id);

            if ($rec->status !== 'brouillon') {
                throw new \RuntimeException('Ce rapprochement est déjà validé.');
            }

            $rec->update([
                'status'       => 'valide',
                'validated_by' => Auth::id(),
                'validated_at' => now(),
            ]);

            return $rec->fresh();
        });
    }

    /**
     * [PRIO-5] Import des lignes de relevé bancaire depuis un fichier CSV.
     *
     * Format attendu (séparateur ; ou ,) :
     *   date;libelle;reference;debit;credit
     *   2026-05-01;Virement client ALPHA;VIR-001;0;500000
     *   2026-05-02;Frais bancaires;COM-MAI;1500;0
     *
     * Les montants sont en entiers FCFA. La 1re ligne (header) est ignorée
     * si elle contient "date" ou "libelle" (case-insensitive).
     */
    public function importCsv(BankReconciliation $rec, \Illuminate\Http\UploadedFile $file): array
    {
        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            throw new \RuntimeException('Impossible d\'ouvrir le fichier CSV.');
        }

        // Détection séparateur
        $firstLine = fgets($handle);
        $separator = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
        rewind($handle);

        $row = 0;
        DB::transaction(function () use ($handle, $separator, $rec, &$imported, &$skipped, &$errors, &$row) {
            while (($data = fgetcsv($handle, 0, $separator)) !== false) {
                $row++;
                if (count($data) < 4) { $skipped++; continue; }

                // Skip header (1re ligne contenant "date" ou "libelle")
                if ($row === 1 && (
                    stripos($data[0], 'date') !== false ||
                    stripos((string) ($data[1] ?? ''), 'libelle') !== false ||
                    stripos((string) ($data[1] ?? ''), 'libellé') !== false
                )) {
                    $skipped++;
                    continue;
                }

                $dateStr = trim($data[0]);
                try {
                    $date = str_contains($dateStr, '/')
                        ? \Carbon\Carbon::createFromFormat('d/m/Y', $dateStr)
                        : \Carbon\Carbon::parse($dateStr);
                } catch (\Throwable $e) {
                    $errors[] = "Ligne $row : date invalide « $dateStr »";
                    $skipped++;
                    continue;
                }

                $label = trim((string) ($data[1] ?? ''));
                if ($label === '') { $skipped++; continue; }

                $reference = trim((string) ($data[2] ?? '')) ?: null;
                $debit  = (int) round((float) str_replace([' ', ','], ['', '.'], (string) ($data[3] ?? '0')));
                $credit = (int) round((float) str_replace([' ', ','], ['', '.'], (string) ($data[4] ?? '0')));

                if ($debit === 0 && $credit === 0) { $skipped++; continue; }

                \App\Models\BankStatementLine::create([
                    'bank_reconciliation_id' => $rec->id,
                    'value_date'             => $date->toDateString(),
                    'label'                  => substr($label, 0, 255),
                    'reference'              => $reference ? substr($reference, 0, 255) : null,
                    'debit'                  => $debit,
                    'credit'                 => $credit,
                    'is_matched'             => false,
                    'sort_order'             => $imported,
                ]);
                $imported++;
            }
        });

        fclose($handle);

        return compact('imported', 'skipped', 'errors');
    }

    /**
     * [PRIO-5] Pré-matching automatique : apparie les lignes de relevé avec
     * les lignes comptables ayant le même montant et une date à ±3 jours.
     * Convention : débit bancaire = crédit du compte 521 (sortie de caisse).
     */
    public function autoMatch(BankReconciliation $rec): int
    {
        $matched = 0;

        $unmatchedStmt = \App\Models\BankStatementLine::where('bank_reconciliation_id', $rec->id)
            ->where('is_matched', false)
            ->get();
        if ($unmatchedStmt->isEmpty()) return 0;

        $cashAccount = $rec->cashAccount;
        if (!$cashAccount) return 0;

        $candidates = $this->getUnmatchedJournalLines(
            $cashAccount,
            $rec->statement_date->copy()->subDays(90)->toDateString(),
            $rec->statement_date->toDateString()
        );

        $used = [];
        foreach ($unmatchedStmt as $stmt) {
            $stmtDebit  = (int) $stmt->debit;
            $stmtCredit = (int) $stmt->credit;
            $stmtDate   = \Carbon\Carbon::parse($stmt->value_date);

            $match = $candidates->first(function ($jl) use ($stmtDebit, $stmtCredit, $stmtDate, $used) {
                if (in_array($jl->id, $used, true)) return false;
                // Débit bancaire = sortie = CRÉDIT du compte 521 ; crédit bancaire = entrée = DÉBIT 521
                $sameAmount = ($stmtDebit > 0 && (int) $jl->credit === $stmtDebit)
                           || ($stmtCredit > 0 && (int) $jl->debit === $stmtCredit);
                if (!$sameAmount) return false;
                $jlDate = \Carbon\Carbon::parse($jl->journalEntry->entry_date);
                return abs($jlDate->diffInDays($stmtDate, false)) <= 3;
            });

            if ($match) {
                $this->matchLine($stmt, $match->id);
                $used[] = $match->id;
                $matched++;
            }
        }

        return $matched;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Get unmatched journal lines for a cash account (for matching panel)
    // ─────────────────────────────────────────────────────────────────────────
    public function getUnmatchedJournalLines(CashAccount $account, string $dateFrom, string $dateTo): \Illuminate\Support\Collection
    {
        // [FIX-RAPPR-03] Filter GL lines to those belonging to THIS cash account's GL account.
        // Strategy: match by account code prefix based on cash account type, then prefer
        // exact code match if a GL account with the same code exists.
        $glCodePrefix = match ($account->type) {
            'caisse'       => '571',
            'mobile_money' => '571',
            default        => '521',  // banque
        };

        return JournalEntryLine::with(['journalEntry.journalType'])
            ->whereHas('account', function ($q) use ($account, $glCodePrefix) {
                // Try exact match on cash account code first, fall back to GL class prefix
                $q->where(function ($sub) use ($account, $glCodePrefix) {
                    $sub->where('code', $account->code)              // exact code match (e.g. '521001')
                        ->orWhere('code', 'like', $glCodePrefix.'%');// class prefix fallback (e.g. '521%')
                });
            })
            ->whereHas('journalEntry', fn ($q) => $q
                ->where('status', 'valide')
                ->whereBetween('entry_date', [$dateFrom, $dateTo])
            )
            ->where(function ($q) {
                // Show lines with no ref OR lines already matched to THIS reconciliation only
                $q->whereNull('reconciliation_ref')
                  ->orWhere('reconciliation_ref', 'not like', 'RAPPR-%');
            })
            ->orderBy('id')
            ->get();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private
    // ─────────────────────────────────────────────────────────────────────────
    private function recalculate(BankReconciliation $rec): void
    {
        $rec->load('lines');
        $totalBankDebit  = (int) $rec->lines->sum('debit');
        $totalBankCredit = (int) $rec->lines->sum('credit');
        $closingBalance  = $rec->opening_balance + $totalBankCredit - $totalBankDebit;

        $rec->update([
            'closing_balance' => $closingBalance,
            'difference'      => $closingBalance - $rec->book_balance,
        ]);
    }
}
