<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TresorerieDashboardController extends Controller
{
    public function __invoke(): View
    {
        $this->authorize('viewAny', \App\Models\ClientPayment::class);

        $company   = currentCompany();
        $companyId = $company->id;
        $now       = now();
        $year      = $now->year;
        $month     = $now->month;

        // ── 1. Position de trésorerie ──────────────────────────────────────────
        $accounts = DB::table('cash_accounts')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->select('id', 'name', 'type', 'current_balance')
            ->orderBy('type')->orderBy('name')
            ->get();

        $positionTotale = $accounts->sum('current_balance');

        // ── 2. Flux du mois courant ────────────────────────────────────────────
        $encaissMois = DB::table('client_payments')
            ->where('company_id', $companyId)
            ->where('status', 'confirme')
            ->whereYear('payment_date', $year)
            ->whereMonth('payment_date', $month)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(amount), 0) as total')
            ->first();

        $decaissMois = DB::table('supplier_payments')
            ->where('company_id', $companyId)
            ->where('status', 'confirme')
            ->whereYear('payment_date', $year)
            ->whereMonth('payment_date', $month)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(amount), 0) as total')
            ->first();

        $fluxNetMois = $encaissMois->total - $decaissMois->total;

        // ── 3. Créances clients (factures non encaissées) ─────────────────────
        $creancesClients = DB::table('invoices')
            ->where('company_id', $companyId)
            ->whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(remaining_amount), 0) as total')
            ->first();

        // Dont factures en retard
        $creancesEnRetard = DB::table('invoices')
            ->where('company_id', $companyId)
            ->where('status', 'en_retard')
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(remaining_amount), 0) as total')
            ->first();

        // ── 4. Effets de commerce en attente ──────────────────────────────────
        $effetsEnAttente = DB::table('commercial_effects')
            ->where('company_id', $companyId)
            ->whereIn('status', ['en_attente', 'accepte'])
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(amount), 0) as total')
            ->first();

        // ── 5. Échéances proches (7 jours) ─────────────────────────────────────
        $echeancesProches = DB::table('invoices')
            ->join('clients', 'clients.id', '=', 'invoices.client_id')
            ->where('invoices.company_id', $companyId)
            ->whereIn('invoices.status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])
            ->whereBetween('invoices.due_at', [
                $now->toDateString(),
                $now->copy()->addDays(7)->toDateString(),
            ])
            ->select(
                'invoices.id',
                'invoices.number',
                'invoices.due_at',
                'invoices.remaining_amount',
                'invoices.status',
                'clients.name as client_name'
            )
            ->orderBy('invoices.due_at')
            ->limit(8)
            ->get();

        // ── 6. Derniers encaissements ──────────────────────────────────────────
        $derniersEncaissements = DB::table('client_payments')
            ->join('clients', 'clients.id', '=', 'client_payments.client_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'client_payments.payment_method_id')
            ->where('client_payments.company_id', $companyId)
            ->where('client_payments.status', 'confirme')
            ->select(
                'client_payments.id',
                'client_payments.reference',
                'client_payments.amount',
                'client_payments.payment_date',
                'clients.name as client_name',
                'payment_methods.name as payment_method_name'
            )
            ->orderByDesc('client_payments.payment_date')
            ->orderByDesc('client_payments.id')
            ->limit(5)
            ->get();

        // ── 7. Flux 6 derniers mois (chart) ────────────────────────────────────
        $chartMois    = [];
        $chartEnc     = [];
        $chartDec     = [];

        for ($i = 5; $i >= 0; $i--) {
            $d = $now->copy()->subMonths($i);
            $y = $d->year;
            $m = $d->month;

            $chartMois[] = $d->translatedFormat('M y');

            $chartEnc[] = (int) DB::table('client_payments')
                ->where('company_id', $companyId)
                ->where('status', 'confirme')
                ->whereYear('payment_date', $y)
                ->whereMonth('payment_date', $m)
                ->sum('amount');

            $chartDec[] = (int) DB::table('supplier_payments')
                ->where('company_id', $companyId)
                ->where('status', 'confirme')
                ->whereYear('payment_date', $y)
                ->whereMonth('payment_date', $m)
                ->sum('amount');
        }

        // ── 8. Répartition par compte (doughnut) ──────────────────────────────
        $compteLabels  = $accounts->pluck('name')->toArray();
        $compteSoldes  = $accounts->map(fn ($a) => max(0, (int) $a->current_balance))->toArray();

        return view('tresorerie.dashboard', compact(
            'accounts',
            'positionTotale',
            'encaissMois',
            'decaissMois',
            'fluxNetMois',
            'creancesClients',
            'creancesEnRetard',
            'effetsEnAttente',
            'echeancesProches',
            'derniersEncaissements',
            'chartMois',
            'chartEnc',
            'chartDec',
            'compteLabels',
            'compteSoldes',
            'now',
        ));
    }
}
