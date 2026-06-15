<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Reception;
use App\Modules\Production\Services\CoilReceptionService;
use Illuminate\Http\RedirectResponse;

class CoilReceptionController extends Controller
{
    public function __construct(private CoilReceptionService $service)
    {
        $this->middleware('permission:production.create');
    }

    /** Génère les bobines matière depuis une réception fournisseur validée. */
    public function generate(Reception $reception): RedirectResponse
    {
        try {
            $coils = $this->service->createFromReception($reception);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()
            ->route('production.coils.index')
            ->with('success', count($coils) . ' bobine(s) créée(s) depuis la réception ' . $reception->number . '.');
    }
}
