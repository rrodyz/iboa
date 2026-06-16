<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Models\SupplierInvoice;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * [TRESO] Échéancier fournisseurs — dettes à payer par date d'échéance.
 * Dérivé directement des factures fournisseurs non soldées (pas de table dédiée).
 */
class SupplierDueController extends Controller
{
    /** Statuts de factures fournisseurs considérées comme dues (non soldées, non annulées). */
    private array $dueStatuses = ['recue', 'validee', 'partiellement_payee'];

    public function upcoming(Request $request): View
    {
        $window = (int) $request->query('window', 30);
        if (!in_array($window, [7, 14, 30, 60, 90], true)) {
            $window = 30;
        }

        $today = now()->startOfDay();

        $base = fn () => SupplierInvoice::with('supplier')
            ->whereIn('status', $this->dueStatuses)
            ->whereRaw('(total_ttc - paid_amount) > 0')
            ->whereNotNull('due_at');

        $overdue = $base()
            ->whereDate('due_at', '<', $today)
            ->orderBy('due_at')
            ->get();

        $upcoming = $base()
            ->whereDate('due_at', '>=', $today)
            ->whereDate('due_at', '<=', $today->copy()->addDays($window))
            ->orderBy('due_at')
            ->get();

        // Sans échéance (due_at null) — à régulariser
        $sansEcheance = SupplierInvoice::with('supplier')
            ->whereIn('status', $this->dueStatuses)
            ->whereRaw('(total_ttc - paid_amount) > 0')
            ->whereNull('due_at')
            ->orderByDesc('received_at')
            ->get();

        $remaining = fn ($inv) => (int) ($inv->total_ttc - $inv->paid_amount);

        $totalOverdue  = $overdue->sum($remaining);
        $totalUpcoming = $upcoming->sum($remaining);
        $totalSans     = $sansEcheance->sum($remaining);

        return view('tresorerie.echeancier-fournisseur.upcoming', compact(
            'overdue', 'upcoming', 'sansEcheance',
            'totalOverdue', 'totalUpcoming', 'totalSans', 'window'
        ));
    }
}
