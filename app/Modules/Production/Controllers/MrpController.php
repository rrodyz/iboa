<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Services\MrpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;

/**
 * [PRODUCTION] MRP — réapprovisionnement bobines (matières premières).
 */
class MrpController extends Controller
{
    public function __construct(private MrpService $mrp)
    {
        // [REACT-01D — Phase 15 revalidée] Ces 4 routes sont AUSSI enveloppées
        // par Route::middleware('permission:production.update') au niveau du
        // groupe de routes (routes/web.php, "pilotage production uniquement").
        // Les deux gardes s'appliquent en ET : voir l'index exige donc
        // production.view ET production.update ensemble, jamais l'un seul.
        $this->middleware('permission:production.view')->only(['index', 'ofProposals']);
        $this->middleware('permission:production.update')->only('generate');
        // Generer des OF, c'est creer des documents de production : le droit de
        // consulter le MRP ne suffit pas.
        $this->middleware('permission:production.create')->only('generateOrders');
    }

    public function index(): \Inertia\Response
    {
        // [REACT-01D — trouvaille Phase 4/9] Cet écran est le SEUL "MRP" réel du
        // code : rupture matière (bobines) vs stock_min, propose une DA achat.
        // Il ne fait ni explosion BOM multi-niveaux, ni pegging, ni time-phasing,
        // ni besoin brut/net par article fini — ces notions n'existent pas ici
        // (BomExplosionService ne sert que la fiche BOM ; MrpPeggingService
        // n'est appelé par aucune route). Le besoin net multi-terme (cible +
        // sécurité + demande − dispo − plan − reçu) est l'écran MTS, déjà migré
        // séparément. Ne pas fusionner : deux moteurs distincts, deux pages.
        $shortfalls = $this->mrp->analyze();

        $stats = [
            'count'     => $shortfalls->count(),
            'deficit'   => (float) $shortfalls->sum('deficit'),
            'estimated' => (int) $shortfalls->sum('estimated'),
        ];

        return Inertia::render('Production/Mrp/Index', [
            'shortfalls' => $shortfalls->map(fn ($s) => [
                'productId' => $s['product_id'],
                'product' => $s['product'],
                'available' => $s['available'],
                'min' => $s['min'],
                'deficit' => $s['deficit'],
                'avgCostPerKg' => $s['avg_cost_per_kg'],
                'estimated' => $s['estimated'],
            ])->values(),
            'stats' => $stats,
            'canGenerate' => request()->user()->can('production.update'),
            'links' => [
                'generateUrl' => route('production.mrp.generate'),
            ],
        ]);
    }

    public function generate(Request $request): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $data = $request->validate([
            'product_ids'   => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $pr = $this->mrp->generatePurchaseRequest($data['product_ids'] ?? []);

        if (! $pr) {
            return back()->with('error', 'Aucun déficit matière à réapprovisionner.');
        }

        // [REACT-01D] La page MRP est maintenant Inertia (SPA) mais la demande
        // d'achat créée reste une page Blade classique. Une redirection Laravel
        // normale serait suivie en fetch() par le client Inertia — qui ne sait
        // pas rendre une réponse HTML non-Inertia et resterait bloqué sur place
        // (bug constaté en validation navigateur : la DA était bien créée en
        // base, mais l'écran ne bougeait jamais). Inertia::location() force un
        // vrai rechargement de page côté client — aucune logique métier changée,
        // seulement la façon dont la redirection est livrée au navigateur. Le
        // flash passe par la session comme avant : il survit au rechargement.
        session()->flash('success', 'Demande d\'achat ' . $pr->number . ' générée (réappro bobines).');

        return \Inertia\Inertia::location(route('achats.demandes-achat.show', $pr));
    }

    /**
     * [MRP] Propositions d'ordre de fabrication — articles fabriques pour le
     * stock dont le besoin net est positif et dont la nomenclature est active.
     */
    public function ofProposals(): View
    {
        $proposals = $this->mrp->productionProposals();

        return view('production.mrp.of', [
            'proposals' => $proposals,
            'stats'     => [
                'count'  => $proposals->count(),
                'besoin' => (float) $proposals->sum('besoin'),
            ],
        ]);
    }

    public function generateOrders(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_ids'   => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $result = $this->mrp->generateProductionOrders($data['product_ids'] ?? []);
        $n = count($result['created']);

        if ($n === 0 && $result['skipped'] === []) {
            return back()->with('error', 'Aucune proposition d’ordre de fabrication à générer.');
        }

        $message = $n . ' ordre(s) de fabrication créé(s) depuis le calcul des besoins.';

        // Les refus ne sont pas tus : on nomme l'article et le motif.
        if ($result['skipped'] !== []) {
            $details = collect($result['skipped'])
                ->map(fn ($s) => $s['produit'] . ' — ' . $s['raison'])->implode(' | ');

            return back()->with($n > 0 ? 'success' : 'error', $message)
                ->with('warning', count($result['skipped']) . ' refusé(s) : ' . $details);
        }

        return redirect()->route('production.orders.index')->with('success', $message);
    }
}
