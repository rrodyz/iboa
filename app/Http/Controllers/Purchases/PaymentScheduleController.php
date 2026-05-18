<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\PaymentSchedule;
use App\Models\SupplierInvoice;
use App\Services\PaymentScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentScheduleController extends Controller
{
    public function __construct(private PaymentScheduleService $service)
    {
        $this->middleware('can:supplier_invoices.view')->only(['upcoming']);
        $this->middleware('can:supplier_invoices.create')->only(['store', 'storeCustom', 'destroy']);
    }

    /**
     * Liste globale des échéances à venir / en retard.
     */
    public function upcoming(Request $request): View
    {
        $window = max(7, min(180, $request->integer('window', 30)));
        $today  = now()->toDateString();
        $limit  = now()->addDays($window)->toDateString();

        $overdue = PaymentSchedule::with(['supplierInvoice.supplier'])
            ->whereIn('status', ['en_attente', 'partiel'])
            ->where('due_date', '<', $today)
            ->orderBy('due_date')
            ->get();

        $upcoming = PaymentSchedule::with(['supplierInvoice.supplier'])
            ->whereIn('status', ['en_attente', 'partiel'])
            ->whereBetween('due_date', [$today, $limit])
            ->orderBy('due_date')
            ->get();

        $totalOverdue  = $overdue->sum(fn($s) => (float) ($s->amount - $s->paid_amount));
        $totalUpcoming = $upcoming->sum(fn($s) => (float) ($s->amount - $s->paid_amount));

        return view('achats.schedules.upcoming', compact('overdue', 'upcoming', 'totalOverdue', 'totalUpcoming', 'window'));
    }

    /**
     * Crée un cadencier "templates" (% + jours) pour une facture donnée.
     */
    public function store(Request $request, SupplierInvoice $facturesFournisseur): RedirectResponse
    {
        $data = $request->validate([
            'installments'              => ['required','array','min:1'],
            'installments.*.percent'    => ['required','numeric','gt:0','lte:100'],
            'installments.*.days_after' => ['required','integer','min:0'],
            'installments.*.label'      => ['nullable','string','max:255'],
        ]);

        try {
            $this->service->createFromInstallments($facturesFournisseur, $data['installments']);
            return back()->with('success', 'Cadencier de paiement créé.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Crée un cadencier custom (dates + montants explicites).
     */
    public function storeCustom(Request $request, SupplierInvoice $facturesFournisseur): RedirectResponse
    {
        $data = $request->validate([
            'rows'             => ['required','array','min:1'],
            'rows.*.due_date'  => ['required','date'],
            'rows.*.amount'    => ['required','numeric','gt:0'],
            'rows.*.label'     => ['nullable','string','max:255'],
        ]);

        try {
            $this->service->createCustom($facturesFournisseur, $data['rows']);
            return back()->with('success', 'Cadencier de paiement personnalisé créé.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(PaymentSchedule $schedule): RedirectResponse
    {
        if ($schedule->paid_amount > 0) {
            return back()->with('error', "Cette échéance a déjà reçu un paiement (impossible à supprimer).");
        }
        $schedule->delete();
        return back()->with('success', 'Échéance supprimée.');
    }
}
