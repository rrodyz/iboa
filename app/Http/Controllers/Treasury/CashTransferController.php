<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\CashTransfer;
use App\Services\CashTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashTransferController extends Controller
{
    public function __construct(private CashTransferService $service) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['from', 'to', 'cash_account_id']);

        $transfers = CashTransfer::with(['fromAccount', 'toAccount', 'createdBy'])
            ->when($filters['cash_account_id'] ?? null, fn ($q, $v) =>
                $q->where(fn ($sub) => $sub->where('from_cash_account_id', $v)->orWhere('to_cash_account_id', $v)))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('transfer_date', '>=', $v))
            ->when($filters['to']   ?? null, fn ($q, $v) => $q->whereDate('transfer_date', '<=', $v))
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Total des virements validés sur le périmètre filtré (hors annulés, tous résultats)
        $baseFilter = CashTransfer::query()
            ->when($filters['cash_account_id'] ?? null, fn ($q, $v) =>
                $q->where(fn ($sub) => $sub->where('from_cash_account_id', $v)->orWhere('to_cash_account_id', $v)))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('transfer_date', '>=', $v))
            ->when($filters['to']   ?? null, fn ($q, $v) => $q->whereDate('transfer_date', '<=', $v));

        $stats = [
            'total'   => (int) (clone $baseFilter)->where('status', 'valide')->sum('amount'),
            'count'   => (clone $baseFilter)->where('status', 'valide')->count(),
            'annules' => (clone $baseFilter)->where('status', 'annule')->count(),
        ];

        $cashAccounts = CashAccount::where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']);

        return view('tresorerie.virements.index', compact('transfers', 'cashAccounts', 'filters', 'stats'));
    }

    public function create(): View
    {
        $cashAccounts = CashAccount::where('is_active', true)
            ->orderBy('type')->orderBy('name')
            ->get(['id', 'name', 'type', 'current_balance']);

        return view('tresorerie.virements.create', compact('cashAccounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_cash_account_id' => ['required', 'integer', 'exists:cash_accounts,id', 'different:to_cash_account_id'],
            'to_cash_account_id'   => ['required', 'integer', 'exists:cash_accounts,id'],
            'amount'               => ['required', 'integer', 'min:1'],
            'transfer_date'        => ['required', 'date'],
            'reference'            => ['nullable', 'string', 'max:100'],
            'notes'                => ['nullable', 'string', 'max:1000'],
        ], [
            'from_cash_account_id.different' => 'Le compte source et le compte destination doivent être différents.',
        ]);

        try {
            $transfer = $this->service->create($data);
            return redirect()
                ->route('tresorerie.virements.show', $transfer)
                ->with('success', "Virement {$transfer->number} effectué.");
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(CashTransfer $virement): View
    {
        $virement->load(['fromAccount', 'toAccount', 'createdBy', 'journalEntry']);
        return view('tresorerie.virements.show', compact('virement'));
    }

    public function cancel(Request $request, CashTransfer $virement): RedirectResponse
    {
        $data = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:500'],
        ], ['motif.required' => "Le motif d'annulation est obligatoire."]);

        try {
            $this->service->cancel($virement, $data['motif']);
            return back()->with('success', "Virement {$virement->number} annulé — fonds restaurés.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
