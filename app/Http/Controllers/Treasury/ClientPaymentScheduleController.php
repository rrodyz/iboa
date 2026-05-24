<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Models\ClientPaymentSchedule;
use App\Models\Invoice;
use App\Services\ClientPaymentScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientPaymentScheduleController extends Controller
{
    public function __construct(
        protected ClientPaymentScheduleService $service
    ) {}

    /**
     * Vue récapitulative : échéances en retard + à venir (fenêtre configurable).
     */
    public function upcoming(Request $request): View
    {
        $window = (int) $request->query('window', 30);
        if (!in_array($window, [7, 14, 30, 60, 90], true)) {
            $window = 30;
        }

        $today = now()->startOfDay();

        $overdue = ClientPaymentSchedule::with(['invoice.client'])
            ->whereIn('status', ['en_attente', 'partiel'])
            ->whereDate('due_date', '<', $today)
            ->orderBy('due_date')
            ->get();

        $upcoming = ClientPaymentSchedule::with(['invoice.client'])
            ->whereIn('status', ['en_attente', 'partiel'])
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', $today->copy()->addDays($window))
            ->orderBy('due_date')
            ->get();

        $totalOverdue  = $overdue->sum(fn ($s) => $s->remainingAmount());
        $totalUpcoming = $upcoming->sum(fn ($s) => $s->remainingAmount());

        return view('tresorerie.echeancier-client.upcoming', compact(
            'overdue', 'upcoming', 'totalOverdue', 'totalUpcoming', 'window'
        ));
    }

    /**
     * Crée un échéancier par tranches (%) depuis la fiche facture.
     * POST /ventes/factures/{facture}/schedules
     */
    public function store(Request $request, Invoice $facture): RedirectResponse
    {
        $data = $request->validate([
            'installments'              => ['required', 'array', 'min:1'],
            'installments.*.percent'    => ['required', 'numeric', 'min:1', 'max:100'],
            'installments.*.days_after' => ['required', 'integer', 'min:0'],
            'installments.*.label'      => ['nullable', 'string', 'max:255'],
        ]);

        // Validate total = 100%
        $totalPct = collect($data['installments'])->sum('percent');
        if (abs($totalPct - 100) > 0.01) {
            return back()->with('error', "Le total des tranches doit être 100 % (actuellement {$totalPct} %).");
        }

        try {
            $this->service->createFromInstallments($facture, $data['installments']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Échéancier créé avec succès.');
    }

    /**
     * Crée un échéancier avec dates + montants explicites.
     * POST /ventes/factures/{facture}/schedules/custom
     */
    public function storeCustom(Request $request, Invoice $facture): RedirectResponse
    {
        $data = $request->validate([
            'rows'             => ['required', 'array', 'min:1'],
            'rows.*.due_date'  => ['required', 'date'],
            'rows.*.amount'    => ['required', 'integer', 'min:1'],
            'rows.*.label'     => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->service->createCustom($facture, $data['rows']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Échéancier personnalisé créé avec succès.');
    }

    /**
     * Supprime toutes les échéances d'une facture.
     * DELETE /ventes/factures/{facture}/schedules
     */
    public function destroyAll(Invoice $facture): RedirectResponse
    {
        ClientPaymentSchedule::where('invoice_id', $facture->id)
            ->whereIn('status', ['en_attente', 'partiel'])
            ->delete();

        return back()->with('success', 'Échéancier supprimé.');
    }

    /**
     * Supprime une seule ligne d'échéance.
     * DELETE /tresorerie/schedules-clients/{schedule}
     */
    public function destroy(ClientPaymentSchedule $schedule): RedirectResponse
    {
        if (in_array($schedule->status, ['paye', 'annule'], true)) {
            return back()->with('error', "Impossible de supprimer une échéance déjà payée ou annulée.");
        }
        $schedule->delete();

        return back()->with('success', 'Échéance supprimée.');
    }
}
