<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * [PERF] Redirection de la racine vers la zone de travail.
 * Contrôleur invokable (et non closure) pour permettre `route:cache`.
 */
class RootRedirectController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route(auth()->check() ? 'dashboard' : 'login');
    }
}
