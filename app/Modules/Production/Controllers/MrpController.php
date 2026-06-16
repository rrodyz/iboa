<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Services\MrpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * [PRODUCTION] MRP — réapprovisionnement bobines (matières premières).
 */
class MrpController extends Controller
{
    public function __construct(private MrpService $mrp)
    {
        $this->middleware('permission:production.view')->only('index');
        $this->middleware('permission:production.update')->only('generate');
    }

    public function index(): View
    {
        $shortfalls = $this->mrp->analyze();

        $stats = [
            'count'     => $shortfalls->count(),
            'deficit'   => (float) $shortfalls->sum('deficit'),
            'estimated' => (int) $shortfalls->sum('estimated'),
        ];

        return view('production.mrp.index', compact('shortfalls', 'stats'));
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_ids'   => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $pr = $this->mrp->generatePurchaseRequest($data['product_ids'] ?? []);

        if (! $pr) {
            return back()->with('error', 'Aucun déficit matière à réapprovisionner.');
        }

        return redirect()
            ->route('achats.demandes-achat.show', $pr)
            ->with('success', 'Demande d\'achat ' . $pr->number . ' générée (réappro bobines).');
    }
}
