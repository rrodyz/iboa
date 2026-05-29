<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PayrollPeriod;
use App\Services\PayrollPeriodService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PayrollPeriodController extends Controller
{
    public function __construct(private readonly PayrollPeriodService $service) {}

    /**
     * Liste toutes les périodes, groupées par année.
     */
    public function index(): View
    {
        $company    = currentCompany();
        $byYear     = $this->service->summaryByYear($company->id);
        $openCount  = PayrollPeriod::forCompany($company->id)->open()->count();
        $lockedCount= PayrollPeriod::forCompany($company->id)->locked()->count();

        return view('rh.parametrage.periodes.index', [
            'company'     => $company,
            'byYear'      => $byYear,
            'openCount'   => $openCount,
            'lockedCount' => $lockedCount,
        ]);
    }

    /**
     * Crée une nouvelle période pour un mois donné.
     * Idempotent : si la période existe déjà, renvoie vers la liste avec info.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $company = currentCompany();
        $date    = Carbon::createFromFormat('Y-m', $request->month)->startOfMonth();

        $existing = PayrollPeriod::where('company_id', $company->id)
                                 ->where('code', $date->format('Y-m'))
                                 ->first();

        if ($existing) {
            return redirect()->route('rh.periodes.index')
                             ->with('info', "La période « {$existing->libelle} » existe déjà (statut : {$existing->status_label}).");
        }

        $period = $this->service->resolveForDate($date, $company->id);

        return redirect()->route('rh.periodes.index')
                         ->with('success', "Période « {$period->libelle} » créée.");
    }

    /**
     * Clôture une période ouverte.
     */
    public function close(PayrollPeriod $periode): RedirectResponse
    {
        $this->authorizeCompany($periode);

        try {
            $this->service->closePeriod($periode);
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Période « {$periode->libelle} » clôturée.");
    }

    /**
     * Réouvre une période clôturée.
     */
    public function reopen(PayrollPeriod $periode): RedirectResponse
    {
        $this->authorizeCompany($periode);

        try {
            $this->service->reopenPeriod($periode);
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Période « {$periode->libelle} » réouverte.");
    }

    /**
     * Verrouille définitivement une période.
     */
    public function lock(Request $request, PayrollPeriod $periode): RedirectResponse
    {
        $this->authorizeCompany($periode);

        try {
            $this->service->lockPeriod($periode);
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Période « {$periode->libelle} » verrouillée — aucune modification ne sera plus possible.");
    }

    /**
     * Déverrouille une période (action dangereuse — justification obligatoire).
     */
    public function unlock(Request $request, PayrollPeriod $periode): RedirectResponse
    {
        $this->authorizeCompany($periode);

        $request->validate([
            'unlock_reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'unlock_reason.required' => 'La justification est obligatoire pour déverrouiller.',
            'unlock_reason.min'      => 'La justification doit comporter au moins 10 caractères.',
        ]);

        try {
            $this->service->unlockPeriod($periode, $request->unlock_reason);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Période « {$periode->libelle} » déverrouillée. Justification enregistrée.");
    }

    /**
     * Archive une période verrouillée (état terminal).
     */
    public function archive(PayrollPeriod $periode): RedirectResponse
    {
        $this->authorizeCompany($periode);

        try {
            $this->service->archivePeriod($periode);
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Période « {$periode->libelle} » archivée.");
    }

    /**
     * Supprime une période ouverte sans bulletins.
     */
    public function destroy(PayrollPeriod $periode): RedirectResponse
    {
        $this->authorizeCompany($periode);

        if (! $periode->isOpen()) {
            return back()->with('error', 'Seules les périodes ouvertes peuvent être supprimées.');
        }

        if ($periode->items()->exists()) {
            $count = $periode->items()->count();
            return back()->with('error', "Impossible de supprimer : {$count} bulletin(s) sont rattachés à cette période.");
        }

        $libelle = $periode->libelle;
        $periode->delete();

        return redirect()->route('rh.periodes.index')
                         ->with('success', "Période « {$libelle} » supprimée.");
    }

    /**
     * API AJAX : statut courant d'une période (pour la UI dynamique).
     */
    public function status(PayrollPeriod $periode): JsonResponse
    {
        $this->authorizeCompany($periode);

        return response()->json([
            'id'           => $periode->id,
            'code'         => $periode->code,
            'libelle'      => $periode->libelle,
            'status'       => $periode->status,
            'status_label' => $periode->status_label,
            'items_count'  => $periode->items()->count(),
            'locked_at'    => $periode->locked_at?->toISOString(),
            'locked_by'    => $periode->lockedBy?->name,
        ]);
    }

    private function authorizeCompany(PayrollPeriod $period): void
    {
        abort_if($period->company_id !== currentCompany()->id, 403);
    }
}
