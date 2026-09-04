<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

/**
 * [A3-QA-013] Le layout Blade (app.js — Turbo/Alpine) et le root Inertia
 * (react.jsx — React) sont deux entrypoints Vite totalement isolés (voir
 * resources/js/react.jsx). Sans marquage, une navigation Turbo entre les
 * deux ne détecte pas que l'entrypoint change : Turbo tente un morph/render
 * SPA-like et insère le nouveau <script type="module"> de façon asynchrone,
 * sans garantir que son exécution précède le rendu du body — l'app React
 * peut alors ne jamais monter (#app vide, aucune erreur console).
 *
 * `data-turbo-track="reload"` est le mécanisme Turbo natif prévu pour ce cas
 * exact : si un élément tracké diffère entre la page courante et la page
 * cible, Turbo abandonne le visit SPA et fait un rechargement complet du
 * navigateur — déterministe, sans course, sans toucher aux ~30 liens de la
 * sidebar. Une navigation Blade→Blade (même entrypoint, tag identique) n'est
 * pas affectée : Turbo continue son morph normal.
 */
class ViteTurboTrackServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Vite::useScriptTagAttributes(['data-turbo-track' => 'reload']);
        Vite::useStyleTagAttributes(['data-turbo-track' => 'reload']);
    }
}
