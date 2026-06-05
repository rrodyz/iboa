<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use App\Services\SigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SigController extends Controller
{
    public function __construct(private SigService $sigService)
    {
        $this->middleware('can:accounting.view');
    }

    public function index(Request $request): View
    {
        $fiscalYears = FiscalYear::orderByDesc('starts_at')->get();
        $fyId = $request->integer('fiscal_year_id')
            ?: optional(FiscalYear::where('is_current', true)->first())->id
            ?: optional($fiscalYears->first())->id;

        $fiscalYear = $fyId ? FiscalYear::find($fyId) : null;
        $companyId  = Auth::user()->company_id;
        if ($fiscalYear && $fiscalYear->company_id !== null && $fiscalYear->company_id !== $companyId) {
            $fiscalYear = null;
        }
        $sig = $fiscalYear ? $this->sigService->compute($fiscalYear) : null;

        return view('comptabilite.rapports.sig', compact('fiscalYears', 'fiscalYear', 'sig'));
    }
}
