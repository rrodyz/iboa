<?php

namespace App\Http\Controllers;

use App\Models\DocumentSequence;
use App\Models\DocumentSequenceAudit;
use App\Services\DocumentSequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class DocumentSequenceController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // Registre des types de documents — libellé + catégorie pour l'UI
    // ──────────────────────────────────────────────────────────────────────────
    private array $registry = [
        // Ventes
        'devis'               => ['label' => 'Devis',                     'category' => 'Ventes'],
        'commande'            => ['label' => 'Commandes clients',          'category' => 'Ventes'],
        'bon_livraison'       => ['label' => 'Bons de livraison',          'category' => 'Ventes'],
        'facture'             => ['label' => 'Factures clients',           'category' => 'Ventes'],
        'avoir'               => ['label' => 'Avoirs clients',             'category' => 'Ventes'],
        // Achats
        'demande_achat'       => ['label' => "Demandes d'achat",           'category' => 'Achats'],
        'commande_achat'      => ['label' => 'Bons de commande fourn.',    'category' => 'Achats'],
        'reception'           => ['label' => 'Réceptions fournisseurs',    'category' => 'Achats'],
        'facture_fournisseur' => ['label' => 'Factures fournisseurs',      'category' => 'Achats'],
        'retour_fournisseur'  => ['label' => 'Retours fournisseurs',       'category' => 'Achats'],
        // Trésorerie
        'encaissement'        => ['label' => 'Encaissements clients',      'category' => 'Trésorerie'],
        'decaissement'        => ['label' => 'Décaissements fournisseurs', 'category' => 'Trésorerie'],
        'remise_banque'       => ['label' => 'Remises en banque',          'category' => 'Trésorerie'],
        'effet_commerce'      => ['label' => 'Effets de commerce',         'category' => 'Trésorerie'],
        // Stock
        'inventaire'          => ['label' => 'Inventaires',                'category' => 'Stock'],
        // Comptabilité
        'ecriture_comptable'  => ['label' => 'Écritures comptables',       'category' => 'Comptabilité'],
        'rapprochement'       => ['label' => 'Rapprochements bancaires',   'category' => 'Comptabilité'],
        'declaration_tva'     => ['label' => 'Déclarations de TVA',        'category' => 'Comptabilité'],
    ];

    public function __construct(private DocumentSequenceService $service)
    {
        $this->middleware('can:settings.manage');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // INDEX
    // ──────────────────────────────────────────────────────────────────────────
    public function index(): View
    {
        $company    = Auth::user()->company;
        $fiscalYear = $company?->currentFiscalYear;

        $sequences = [];
        $maxUsed   = [];   // map type → n° max effectivement consommé
        foreach (array_keys($this->registry) as $type) {
            $seq = DocumentSequence::firstOrCreate(
                [
                    'company_id'     => $company->id,
                    'fiscal_year_id' => $company->current_fiscal_year_id,
                    'document_type'  => $type,
                ],
                $this->service->defaultConfig($type)
            );
            $sequences[$type] = $seq;
            $maxUsed[$type]   = $this->service->maxUsedNumber($seq);
        }

        // Grouper les TYPES par catégorie en préservant les noms.
        // (groupBy() réindexe numériquement les sous-collections ; on construit donc manuellement.)
        $grouped = [];
        foreach ($this->registry as $type => $info) {
            $grouped[$info['category']][] = $type;
        }
        $grouped = collect($grouped);

        $labels = collect($this->registry)->map(fn($d) => $d['label'])->toArray();

        return view('settings.sequences.index', compact(
            'sequences', 'grouped', 'company', 'fiscalYear', 'maxUsed', 'labels'
        ));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // EDIT (page dédiée)
    // ──────────────────────────────────────────────────────────────────────────
    public function edit(DocumentSequence $sequence): View
    {
        $label   = $this->registry[$sequence->document_type]['label'] ?? $sequence->document_type;
        $maxUsed = $this->service->maxUsedNumber($sequence);
        $sequence->load('fiscalYear');

        return view('settings.sequences.edit', compact('sequence', 'label', 'maxUsed'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // UPDATE (format + compteur)
    // ──────────────────────────────────────────────────────────────────────────
    public function update(DocumentSequence $sequence, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prefix'         => 'required|string|max:20',
            'suffix'         => 'nullable|string|max:20',
            'padding'        => 'required|integer|min:1|max:9',
            'include_year'   => 'boolean',
            'year_format'    => 'required|in:4,2',
            'year_separator' => 'nullable|string|max:5',
            'last_number'    => 'nullable|integer|min:0|max:999999999',
            'reason'         => 'nullable|string|max:255',
            'force'          => 'nullable|boolean',
        ]);

        $validated['include_year']   = $request->boolean('include_year');
        $validated['year_separator'] = $validated['year_separator'] ?? '-';
        $validated['suffix']         = $validated['suffix'] ?? null;
        $force                       = (bool) ($validated['force'] ?? false);
        $reason                      = $validated['reason'] ?? null;

        try {
            // ── 1) Format (préfixe, padding, année…) ────────────────────────
            $formatChanges = array_intersect_key($validated, array_flip(DocumentSequence::FORMAT_FIELDS));

            $formatChanged = false;
            foreach ($formatChanges as $k => $v) {
                if ((string) $sequence->$k !== (string) $v) { $formatChanged = true; break; }
            }
            if ($formatChanged) {
                $this->service->updateFormat($sequence, $formatChanges, $reason);
                $sequence->refresh();
            }

            // ── 2) Compteur (seulement si transmis et différent) ────────────
            if (array_key_exists('last_number', $validated) && $validated['last_number'] !== null) {
                $newCounter = (int) $validated['last_number'];
                if ($newCounter !== (int) $sequence->last_number) {
                    $this->service->setCounter($sequence, $newCounter, $reason, $force);
                }
            }
        } catch (RuntimeException $e) {
            return back()->withErrors(['sequence' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Numérotation mise à jour avec succès.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // RESET (compteur → 0)
    // ──────────────────────────────────────────────────────────────────────────
    public function reset(DocumentSequence $sequence, Request $request): RedirectResponse
    {
        $reason = $request->input('reason');
        $force  = $request->boolean('force');

        try {
            $this->service->resetCounter($sequence, $reason, $force);
        } catch (RuntimeException $e) {
            return back()->withErrors(['sequence' => $e->getMessage()]);
        }

        $label = $this->registry[$sequence->document_type]['label'] ?? $sequence->document_type;
        return back()->with('success', "Compteur « {$label} » remis à zéro.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SET COUNTER (raccourci dédié)
    // ──────────────────────────────────────────────────────────────────────────
    public function setCounter(DocumentSequence $sequence, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'last_number' => 'required|integer|min:0|max:999999999',
            'reason'      => 'nullable|string|max:255',
            'force'       => 'nullable|boolean',
        ]);

        try {
            $this->service->setCounter($sequence, (int) $data['last_number'], $data['reason'] ?? null, (bool) ($data['force'] ?? false));
        } catch (RuntimeException $e) {
            return back()->withErrors(['sequence' => $e->getMessage()]);
        }

        $label = $this->registry[$sequence->document_type]['label'] ?? $sequence->document_type;
        $next  = $sequence->fresh()->last_number + 1;

        return back()->with('success', "Compteur « {$label} » défini (prochain n° : {$next}).");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // MODE auto / manuel
    // ──────────────────────────────────────────────────────────────────────────
    public function setMode(DocumentSequence $sequence, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'numbering_mode' => 'required|in:auto,manual',
            'reason'         => 'nullable|string|max:255',
        ]);

        $this->service->setMode($sequence, $data['numbering_mode'], $data['reason'] ?? null);

        return back()->with('success', 'Mode de numérotation mis à jour.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // LOCK / UNLOCK
    // ──────────────────────────────────────────────────────────────────────────
    public function toggleLock(DocumentSequence $sequence, Request $request): RedirectResponse
    {
        $this->service->toggleLock($sequence, $request->input('reason'));
        return back()->with('success', $sequence->fresh()->is_locked ? 'Séquence verrouillée.' : 'Séquence déverrouillée.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // AUDIT (historique d'une séquence)
    // ──────────────────────────────────────────────────────────────────────────
    public function audit(DocumentSequence $sequence): View
    {
        $audits = $sequence->audits()->with('user:id,name')->paginate(30);
        $label  = $this->registry[$sequence->document_type]['label'] ?? $sequence->document_type;

        return view('settings.sequences.audit', compact('sequence', 'audits', 'label'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PREVIEW (AJAX)
    // ──────────────────────────────────────────────────────────────────────────
    public function preview(Request $request): JsonResponse
    {
        $company = Auth::user()->company;
        $type    = $request->query('type', '');

        if ($request->has('prefix')) {
            $seq = new DocumentSequence([
                'prefix'         => $request->input('prefix', ''),
                'suffix'         => $request->input('suffix', ''),
                'padding'        => (int) $request->input('padding', 3),
                'include_year'   => (bool) $request->input('include_year', true),
                'year_format'    => $request->input('year_format', '4'),
                'year_separator' => $request->input('year_separator', '-'),
                'last_number'    => (int) $request->input('last_number', 0),
            ]);
        } else {
            $seq = DocumentSequence::where('company_id', $company->id)
                ->where('fiscal_year_id', $company->current_fiscal_year_id)
                ->where('document_type', $type)
                ->first();

            if (!$seq) {
                $seq = new DocumentSequence($this->service->defaultConfig($type));
                $seq->last_number = 0;
            }
        }

        $preview = $this->service->format($seq, $seq->last_number + 1);

        return response()->json(['preview' => $preview, 'current' => $seq->last_number]);
    }
}
