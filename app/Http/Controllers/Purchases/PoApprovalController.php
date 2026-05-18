<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PoApprovalThreshold;
use App\Models\PurchaseOrder;
use App\Services\PoApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PoApprovalController extends Controller
{
    public function __construct(private PoApprovalService $service)
    {
        $this->middleware('can:purchase_orders.view');
    }

    /**
     * Liste des PO en attente d'approbation pour l'utilisateur courant.
     */
    public function pending(Request $request): View
    {
        $pendingPos = PurchaseOrder::with(['supplier', 'createdBy'])
            ->whereNull('deleted_at')
            ->where('approval_status', 'en_attente')
            ->latest('submitted_for_approval_at')
            ->paginate(20);

        // Annote chaque PO avec la règle applicable et si l'utilisateur peut approuver
        $user = Auth::user();
        foreach ($pendingPos as $po) {
            $po->rule         = $this->service->findRequiredRule($po);
            $po->can_approve  = $po->rule ? $po->rule->canBeApprovedBy($user) : true;
        }

        return view('achats.approval.pending', compact('pendingPos'));
    }

    public function submit(PurchaseOrder $commande): RedirectResponse
    {
        try {
            $this->service->submitForApproval($commande);
            return back()->with('success', "PO {$commande->number} soumis à approbation.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function approve(PurchaseOrder $commande): RedirectResponse
    {
        try {
            $this->service->approve($commande, Auth::user());
            return back()->with('success', "PO {$commande->number} approuvé.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, PurchaseOrder $commande): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        try {
            $this->service->reject($commande, Auth::user(), $data['reason']);
            return back()->with('success', "PO {$commande->number} rejeté.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Gestion des seuils d'approbation (paramétrage).
     * Permission: settings.manage (vérifiée par middleware côté route).
     */
    public function thresholdsIndex(): View
    {
        $thresholds = PoApprovalThreshold::orderBy('sort_order')->orderBy('min_amount')->get();
        return view('achats.approval.thresholds', compact('thresholds'));
    }

    public function thresholdsStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'                => ['required','string','max:100'],
            'min_amount'          => ['required','numeric','min:0'],
            'max_amount'          => ['nullable','numeric','gt:min_amount'],
            'required_role'       => ['nullable','string','max:100'],
            'required_permission' => ['nullable','string','max:100'],
        ]);
        $data['company_id'] = Company::firstOrFail()->id;
        $data['is_active']  = true;
        PoApprovalThreshold::create($data);
        return back()->with('success', 'Règle de seuil enregistrée.');
    }

    public function thresholdsDestroy(PoApprovalThreshold $threshold): RedirectResponse
    {
        $threshold->delete();
        return back()->with('success', 'Règle supprimée.');
    }
}
