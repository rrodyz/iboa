<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\JournalType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * [COMPTA-JT] CRUD des codes journaux (JournalType).
 *
 * Codes standards SYSCOA :
 *   AC (Achats) · VE (Ventes) · BQ (Banque) · CA (Caisse)
 *   OD (Opérations diverses) · AN (À-nouveau)
 *
 * Un code utilisé par au moins une écriture ne peut pas être supprimé — il peut
 * être désactivé (is_active=false) pour empêcher la création d'écritures dessus.
 */
class JournalTypeController extends Controller
{
    private const TYPES = [
        'achat'               => 'Achats',
        'vente'               => 'Ventes',
        'banque'              => 'Banque',
        'caisse'              => 'Caisse',
        'operations_diverses' => 'Opérations diverses',
        'a_nouveau'           => 'À nouveau',
    ];

    public function __construct()
    {
        $this->middleware('can:accounting.view')->only(['index']);
        $this->middleware('can:accounting.write')->except(['index']);
    }

    public function index(): View
    {
        $journalTypes = JournalType::withCount('entries')->orderBy('code')->get();
        $types = self::TYPES;
        return view('comptabilite.journal-types.index', compact('journalTypes', 'types'));
    }

    public function create(): View
    {
        $types = self::TYPES;
        return view('comptabilite.journal-types.create', compact('types'));
    }

    public function store(Request $request): RedirectResponse
    {
        $company = Company::firstOrFail();
        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:10',
                Rule::unique('journal_types', 'code')->where(fn($q) => $q->where('company_id', $company->id)),
            ],
            'name'      => ['required', 'string', 'max:100'],
            'type'      => ['required', Rule::in(array_keys(self::TYPES))],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['code']       = strtoupper(trim($data['code']));
        $data['company_id'] = $company->id;
        $data['is_active']  = $request->boolean('is_active', true);

        JournalType::create($data);
        return redirect()->route('comptabilite.journal-types.index')
            ->with('success', "Code journal « {$data['code']} » créé.");
    }

    public function edit(JournalType $journalType): View
    {
        $types = self::TYPES;
        return view('comptabilite.journal-types.edit', compact('journalType', 'types'));
    }

    public function update(Request $request, JournalType $journalType): RedirectResponse
    {
        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:10',
                Rule::unique('journal_types', 'code')
                    ->where(fn($q) => $q->where('company_id', $journalType->company_id))
                    ->ignore($journalType->id),
            ],
            'name'      => ['required', 'string', 'max:100'],
            'type'      => ['required', Rule::in(array_keys(self::TYPES))],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['code']      = strtoupper(trim($data['code']));
        $data['is_active'] = $request->boolean('is_active', false);

        $journalType->update($data);
        return redirect()->route('comptabilite.journal-types.index')
            ->with('success', "Code journal « {$journalType->code} » mis à jour.");
    }

    public function destroy(JournalType $journalType): RedirectResponse
    {
        // Refuse la suppression si des écritures pointent vers ce journal
        $count = $journalType->entries()->count();
        if ($count > 0) {
            return back()->with('error', sprintf(
                "Impossible de supprimer le journal « %s » : %d écriture(s) l'utilisent. "
                . "Désactivez-le à la place (is_active = false).",
                $journalType->code, $count
            ));
        }

        $code = $journalType->code;
        $journalType->delete();
        return redirect()->route('comptabilite.journal-types.index')
            ->with('success', "Code journal « {$code} » supprimé.");
    }
}
