<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\CashAccount;
use App\Services\BankReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BankReconciliationController extends Controller
{
    public function __construct(private BankReconciliationService $service) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['cash_account_id', 'status', 'date_from', 'date_to']);

        $query = BankReconciliation::with(['cashAccount', 'createdBy'])
            ->when($filters['cash_account_id'] ?? null, fn ($q, $v) => $q->where('cash_account_id', $v))
            ->when($filters['status']          ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['date_from']       ?? null, fn ($q, $v) => $q->where('statement_date', '>=', $v))
            ->when($filters['date_to']         ?? null, fn ($q, $v) => $q->where('statement_date', '<=', $v))
            ->orderByDesc('statement_date')
            ->orderByDesc('id');

        $reconciliations = $query->paginate(20)->withQueryString();
        $cashAccounts    = CashAccount::where('type', 'banque')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);

        return view('comptabilite.rapprochement.index', compact('reconciliations', 'filters', 'cashAccounts'));
    }

    public function create(): View
    {
        $cashAccounts = CashAccount::where('type', 'banque')->where('is_active', true)->orderBy('name')->get();
        return view('comptabilite.rapprochement.create', compact('cashAccounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cash_account_id' => ['required', 'integer', 'exists:cash_accounts,id'],
            'period_start'    => ['required', 'date'],
            'period_end'      => ['required', 'date', 'after_or_equal:period_start'],
            'statement_date'  => ['required', 'date'],
            'opening_balance' => ['required', 'integer'],
            'book_balance'    => ['required', 'integer'],
            'notes'           => ['nullable', 'string', 'max:1000'],
            'lines'           => ['nullable', 'array'],
            'lines.*.value_date' => ['nullable', 'date'],
            'lines.*.label'      => ['required_with:lines', 'string', 'max:255'],
            'lines.*.reference'  => ['nullable', 'string', 'max:100'],
            'lines.*.debit'      => ['nullable', 'integer', 'min:0'],
            'lines.*.credit'     => ['nullable', 'integer', 'min:0'],
        ]);

        $rec = $this->service->create($data);

        return redirect()
            ->route('comptabilite.rapprochement.show', $rec)
            ->with('success', 'Rapprochement ' . $rec->number . ' créé.');
    }

    public function show(BankReconciliation $rapprochement): View
    {
        $rapprochement->load(['cashAccount', 'lines.journalEntryLine.journalEntry', 'createdBy', 'validatedBy']);

        $unmatchedJournalLines = $rapprochement->isEditable()
            ? $this->service->getUnmatchedJournalLines(
                $rapprochement->cashAccount,
                $rapprochement->period_start->toDateString(),
                $rapprochement->period_end->toDateString()
              )
            : collect();

        return view('comptabilite.rapprochement.show', compact('rapprochement', 'unmatchedJournalLines'));
    }

    // ─── AJAX: match a bank line to a journal line ───────────────────────────
    public function matchLine(Request $request, BankStatementLine $line): JsonResponse
    {
        $request->validate(['journal_entry_line_id' => ['required', 'integer', 'exists:journal_entry_lines,id']]);

        try {
            $this->service->matchLine($line, $request->journal_entry_line_id);
            return response()->json(['ok' => true, 'message' => 'Ligne rapprochée.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function unmatchLine(BankStatementLine $line): JsonResponse
    {
        try {
            $this->service->unmatchLine($line);
            return response()->json(['ok' => true, 'message' => 'Correspondance supprimée.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ─── Validate ────────────────────────────────────────────────────────────
    public function validateReconciliation(BankReconciliation $rapprochement): RedirectResponse
    {
        try {
            $this->service->validate($rapprochement);
            return redirect()
                ->route('comptabilite.rapprochement.show', $rapprochement)
                ->with('success', 'Rapprochement validé.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * [PRIO-5] Import des lignes du relevé bancaire depuis un fichier CSV.
     */
    public function importCsv(Request $request, BankReconciliation $rapprochement): RedirectResponse
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        try {
            $result = $this->service->importCsv($rapprochement, $request->file('csv_file'));
            $msg = "{$result['imported']} ligne(s) importée(s), {$result['skipped']} ignorée(s).";
            if (!empty($result['errors'])) {
                $msg .= "\nErreurs : " . implode(' | ', array_slice($result['errors'], 0, 5));
            }
            return back()->with('success', $msg);
        } catch (\Exception $e) {
            return back()->with('error', 'Import CSV : ' . $e->getMessage());
        }
    }

    /**
     * [PRIO-5] Pré-matching automatique des lignes relevé ↔ lignes comptables.
     */
    public function autoMatch(BankReconciliation $rapprochement): RedirectResponse
    {
        try {
            $matched = $this->service->autoMatch($rapprochement);
            return back()->with('success',
                $matched === 0
                    ? 'Aucune correspondance automatique trouvée (montants ou dates trop éloignés).'
                    : "{$matched} ligne(s) appariée(s) automatiquement."
            );
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
