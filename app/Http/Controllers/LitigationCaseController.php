<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\LitigationCase;
use App\Services\LitigationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LitigationCaseController extends Controller
{
    public function __construct(private LitigationService $service)
    {
        $this->middleware('permission:clients.view')->only('index');
        $this->middleware('permission:clients.create')->only(['store', 'update']);
        $this->middleware('permission:clients.delete')->only('destroy');
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['client_id', 'status']);

        $cases = LitigationCase::with(['client', 'invoice', 'createdBy'])
            ->when($filters['client_id'] ?? null, fn ($q, $v) => $q->where('client_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByRaw("CASE status WHEN 'ouvert' THEN 0 WHEN 'en_cours' THEN 1 WHEN 'suspendu' THEN 2 WHEN 'irrecouvrable' THEN 3 ELSE 4 END")
            ->orderByDesc('opened_at')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'ouverts'       => LitigationCase::whereIn('status', ['ouvert', 'en_cours', 'suspendu'])->count(),
            'montant'       => (int) LitigationCase::whereIn('status', ['ouvert', 'en_cours', 'suspendu'])->sum('amount'),
            'recouvres'     => LitigationCase::where('status', 'recouvre')->count(),
            'irrecouvrable' => (int) LitigationCase::where('status', 'irrecouvrable')->sum('amount'),
        ];

        $clients = Client::active()->orderBy('name')->get(['id', 'name', 'trade_name']);

        return view('relances.contentieux.index', compact('cases', 'stats', 'filters', 'clients'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id'  => ['required', 'exists:clients,id'],
            'invoice_id' => ['nullable', 'exists:invoices,id'],
            'amount'     => ['required', 'integer', 'min:1'],
            'stage'      => ['required', 'in:mise_en_demeure,huissier,avocat,tribunal,abandon'],
            'opened_at'  => ['required', 'date'],
            'notes'      => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $case = $this->service->create($data);
            return back()->with('success', "Dossier contentieux {$case->number} ouvert.");
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function update(Request $request, LitigationCase $case): RedirectResponse
    {
        $data = $request->validate([
            'stage'  => ['nullable', 'in:mise_en_demeure,huissier,avocat,tribunal,abandon'],
            'status' => ['nullable', 'in:ouvert,en_cours,suspendu,recouvre,irrecouvrable'],
            'notes'  => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $case = $this->service->update($case, $data);
            $msg  = $case->status === 'irrecouvrable'
                ? "Dossier {$case->number} : créance passée en perte (écriture 6514/411)."
                : "Dossier {$case->number} mis à jour.";
            return back()->with('success', $msg);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(LitigationCase $case): RedirectResponse
    {
        if ($case->journal_entry_id) {
            return back()->with('error', 'Impossible de supprimer un dossier déjà passé en perte comptable.');
        }
        $case->delete();
        return back()->with('success', 'Dossier supprimé.');
    }
}
