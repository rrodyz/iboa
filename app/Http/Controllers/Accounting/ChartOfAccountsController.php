<?php

namespace App\Http\Controllers\Accounting;

use App\Exports\Accounting\AccountsExport;
use App\Http\Controllers\Controller;
use App\Imports\AccountsImport;
use App\Models\Account;
use App\Models\AccountClass;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChartOfAccountsController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:accounting.view')->only(['index', 'show']);
        $this->middleware('can:accounting.manage')->except(['index', 'show']);
    }

    public function index(Request $request): View
    {
        $company = currentCompany();
        $search  = $request->input('search');
        $classId = $request->input('class_id');

        $accounts = Account::with(['accountClass', 'parent'])
            ->where('company_id', $company->id)
            ->when($search, fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('code', 'like', '%' . $search . '%')
                   ->orWhere('name', 'like', '%' . $search . '%');
            }))
            ->when($classId, fn ($q) => $q->where('account_class_id', $classId))
            ->orderBy('code')
            ->paginate(50)
            ->withQueryString();

        $classes = AccountClass::where('company_id', $company->id)
            ->orderBy('number')
            ->get();

        return view('comptabilite.plan-comptable.index', compact('accounts', 'classes', 'search', 'classId'));
    }

    public function create(): View
    {
        $company = currentCompany();
        $classes = AccountClass::where('company_id', $company->id)->orderBy('number')->get();
        $parents = Account::where('company_id', $company->id)
            ->where('is_detail', false)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return view('comptabilite.plan-comptable.create', compact('classes', 'parents'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account_class_id' => ['required', 'integer', 'exists:account_classes,id'],
            'parent_id'        => ['nullable', 'integer', 'exists:accounts,id'],
            'code'             => ['required', 'string', 'max:20'],
            'name'             => ['required', 'string', 'max:255'],
            'type'             => ['required', 'in:actif,passif,charge,produit,bilan,resultat'],
            'is_detail'        => ['boolean'],
        ]);

        $company = currentCompany();
        $data['company_id'] = $company->id;
        $data['is_detail']  = $request->boolean('is_detail', true);
        $data['is_active']  = true;

        Account::create($data);

        return redirect()
            ->route('comptabilite.plan-comptable.index')
            ->with('success', 'Compte créé avec succès.');
    }

    public function edit(Account $account): View
    {
        $company = currentCompany();
        $classes = AccountClass::where('company_id', $company->id)->orderBy('number')->get();
        $parents = Account::where('company_id', $company->id)
            ->where('is_detail', false)
            ->where('id', '!=', $account->id)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return view('comptabilite.plan-comptable.edit', compact('account', 'classes', 'parents'));
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $data = $request->validate([
            'account_class_id' => ['required', 'integer', 'exists:account_classes,id'],
            'parent_id'        => ['nullable', 'integer', 'exists:accounts,id'],
            'name'             => ['required', 'string', 'max:255'],
            'type'             => ['required', 'in:actif,passif,charge,produit,bilan,resultat'],
            'is_detail'        => ['boolean'],
            'is_active'        => ['boolean'],
        ]);

        $data['is_detail'] = $request->boolean('is_detail');
        $data['is_active'] = $request->boolean('is_active');

        $account->update($data);

        return redirect()
            ->route('comptabilite.plan-comptable.index')
            ->with('success', 'Compte mis à jour.');
    }

    // ── Export ────────────────────────────────────────────────────────────────

    public function export(Request $request): BinaryFileResponse
    {
        $company = currentCompany();
        $classId = $request->integer('class_id') ?: null;

        return Excel::download(
            new AccountsExport($company->id, $classId),
            'plan-comptable-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function exportPdf(Request $request): mixed
    {
        $company = currentCompany();
        $search  = $request->input('search');
        $classId = $request->input('class_id');

        $accounts = Account::with(['accountClass', 'parent'])
            ->where('company_id', $company->id)
            ->when($search, fn($q) => $q->where(fn($q2) =>
                $q2->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')
            ))
            ->when($classId, fn($q) => $q->where('account_class_id', $classId))
            ->orderBy('code')
            ->get();

        $classes = AccountClass::where('company_id', $company->id)->orderBy('number')->get();

        $pdf = Pdf::loadView('comptabilite.pdf.plan-comptable', compact(
            'company', 'accounts', 'classes', 'search', 'classId'
        ))->setPaper('a4', 'portrait');

        return $pdf->download('plan_comptable_' . now()->format('Ymd_His') . '.pdf');
    }

    // ── Import ────────────────────────────────────────────────────────────────

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ], [
            'file.required' => 'Veuillez sélectionner un fichier.',
            'file.mimes'    => 'Le fichier doit être au format Excel (.xlsx, .xls) ou CSV.',
            'file.max'      => 'Le fichier ne doit pas dépasser 5 Mo.',
        ]);

        $company = currentCompany();
        $import  = new AccountsImport($company->id);

        Excel::import($import, $request->file('file'));

        $msg = "{$import->imported} compte(s) importé(s)";
        if ($import->skipped > 0) {
            $msg .= ", {$import->skipped} ligne(s) ignorée(s)";
        }
        if (! empty($import->errorLines)) {
            $msg .= '. Erreurs : ' . implode(' | ', array_slice($import->errorLines, 0, 5));
        }

        return redirect()
            ->route('comptabilite.plan-comptable.index')
            ->with('success', $msg . '.');
    }

    // ── Template ──────────────────────────────────────────────────────────────

    public function template(): BinaryFileResponse
    {
        // Returns a minimal Excel template with the correct column headers
        return Excel::download(
            new class implements \Maatwebsite\Excel\Concerns\FromArray,
                                 \Maatwebsite\Excel\Concerns\WithHeadings,
                                 \Maatwebsite\Excel\Concerns\WithTitle,
                                 \Maatwebsite\Excel\Concerns\ShouldAutoSize,
                                 \Maatwebsite\Excel\Concerns\WithStyles
            {
                public function title(): string { return 'Plan comptable'; }
                public function headings(): array
                {
                    return ['code', 'libelle', 'classe', 'type', 'parent', 'saisissable', 'actif'];
                }
                public function array(): array
                {
                    return [
                        ['411000', 'Clients', '4', 'actif',   '',       'Oui', 'Oui'],
                        ['411001', 'Client A', '4', 'actif',  '411000', 'Oui', 'Oui'],
                        ['601000', 'Achats', '6', 'charge',   '',       'Oui', 'Oui'],
                    ];
                }
                public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
                {
                    return [
                        1 => [
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '7C3AED']],
                        ],
                    ];
                }
            },
            'modele-plan-comptable.xlsx'
        );
    }
}
