<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $companyId = Auth::user()->company_id;

        // KPIs
        $totalContacts   = CrmContact::forCompany($companyId)->count();
        $newThisMonth    = CrmContact::forCompany($companyId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $openOpps        = CrmOpportunity::forCompany($companyId)->active()->count();
        $pipeline        = CrmOpportunity::forCompany($companyId)->active()->sum('amount');
        $wonThisMonth    = CrmOpportunity::forCompany($companyId)
            ->where('stage', 'gagne')
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->sum('amount');
        $overdueActivities = CrmActivity::forCompany($companyId)->overdue()->count();

        // Pipeline par stage — 1 seule requête GROUP BY au lieu de N (évite N+1)
        $stageRows = CrmOpportunity::forCompany($companyId)
            ->whereNotNull('stage')
            ->selectRaw('stage, COUNT(*) as cnt, COALESCE(SUM(amount), 0) as total_amount')
            ->groupBy('stage')
            ->get()
            ->keyBy('stage');

        $stageStats = [];
        foreach (array_keys(CrmOpportunity::STAGES) as $stage) {
            $row = $stageRows->get($stage);
            $stageStats[$stage] = [
                'count'  => $row ? (int) $row->cnt : 0,
                'amount' => $row ? (float) $row->total_amount : 0.0,
                'config' => CrmOpportunity::STAGES[$stage],
            ];
        }

        // Activités à faire (triées par priorité puis date)
        $pendingActivities = CrmActivity::forCompany($companyId)
            ->pending()
            ->with(['contact', 'opportunity'])
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END")
            ->orderBy('due_at')
            ->limit(10)
            ->get();

        // Derniers contacts créés
        $recentContacts = CrmContact::forCompany($companyId)
            ->with('user')
            ->latest()
            ->limit(5)
            ->get();

        // Top opportunités par montant
        $topOpps = CrmOpportunity::forCompany($companyId)
            ->active()
            ->with('contact')
            ->orderByDesc('amount')
            ->limit(5)
            ->get();

        return view('crm.dashboard', compact(
            'totalContacts', 'newThisMonth', 'openOpps', 'pipeline',
            'wonThisMonth', 'overdueActivities', 'stageStats',
            'pendingActivities', 'recentContacts', 'topOpps'
        ));
    }
}
