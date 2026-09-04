<?php

/**
 * [A3-QA-014] Régression structurelle : restauration Turbo d'un snapshot Alpine.
 *
 * Observation : après une navigation Turbo Blade→Blade puis « retour arrière »
 * (restauration depuis le cache Turbo), la console affiche
 * « Uncaught ReferenceError: fn is not defined » (topbar sageSearch), et le bloc
 * « Fonctions » de la recherche globale est dupliqué.
 *
 * Root cause (PROUVÉE en navigateur — pas dans le template) : Turbo met en
 * cache un clone du DOM vivant, qui contient déjà les clones générés par
 * x-if/x-for (ex. les 8 entrées « fn » de la recherche globale, toujours
 * rendues puisque matchedFunctions retourne 8 fonctions par défaut). À la
 * restauration, Alpine ré-initialise ces clones comme des éléments ordinaires,
 * hors du scope de boucle → `fn` n'existe pas.
 *
 * _topbar.blade.php N'EST PAS la cause : chaque référence à `fn` y est bien
 * située dans <template x-for="fn in matchedFunctions">.
 *
 * Fix : resources/js/app.js — Alpine.destroyTree(document.body) en fin du
 * handler `turbo:before-cache` (les cleanups x-if/x-for retirent les clones
 * avant que Turbo clone le DOM ; le snapshot redevient le HTML serveur).
 *
 * Aucun runner navigateur automatisé n'existe dans le projet : la preuve
 * navigateur est manuelle mais reproductible (rapport A3-QA-014). Ce test fige
 * le contrat côté source.
 */

function qa014AppJs(): string
{
    return file_get_contents(base_path('resources/js/app.js'));
}

function qa014BeforeCacheHandlers(): array
{
    preg_match_all(
        "/addEventListener\(\s*'turbo:before-cache'\s*,\s*function\s*\(\)\s*\{(?<body>.*?)\n\}\);/s",
        qa014AppJs(),
        $m,
        PREG_OFFSET_CAPTURE
    );

    return $m;
}

it('QA-014 — app.js enregistre un seul handler turbo:before-cache et il détruit l\'arbre Alpine', function () {
    $m = qa014BeforeCacheHandlers();

    expect($m['body'])->toHaveCount(1);

    $body = $m['body'][0][0];

    expect($body)->toMatch('/Alpine\.destroyTree\(\s*document\.body\s*\)/');
});

it('QA-014 — le handler turbo:before-cache est déclaré après l\'import Alpine (identifiant résolu)', function () {
    $js = qa014AppJs();
    $m  = qa014BeforeCacheHandlers();

    $importPos  = strpos($js, "import Alpine from 'alpinejs'");
    $handlerPos = $m['body'][0][1];

    expect($importPos)->not->toBeFalse()
        ->and($importPos)->toBeLessThan($handlerPos);
});

it('QA-014 — le nettoyage Alpine est exécuté en dernier, après les cleanups de page et DataTables', function () {
    $body = qa014BeforeCacheHandlers()['body'][0][0];

    $alpinePos    = strpos($body, 'Alpine.destroyTree');
    $cleanupsPos  = strrpos($body, '__turboCleanups');
    $dataTablePos = strpos($body, 'DataTable');

    expect($cleanupsPos)->not->toBeFalse()
        ->and($dataTablePos)->not->toBeFalse()
        ->and($alpinePos)->toBeGreaterThan($cleanupsPos)
        ->and($alpinePos)->toBeGreaterThan($dataTablePos);
});

it('QA-014 — _topbar.blade.php : toute référence à `fn` est dans le scope x-for="fn in matchedFunctions" (NOT ROOT CAUSE)', function () {
    $tpl = file_get_contents(base_path('resources/views/partials/layout/_topbar.blade.php'));

    preg_match_all('/<template x-for="fn in matchedFunctions"[^>]*>(?<inner>.*?)<\/template>/s', $tpl, $loops);

    expect($loops['inner'])->toHaveCount(1);

    $inner = $loops['inner'][0];

    expect($inner)->toContain('fn.url')
        ->and($inner)->toContain('fn.label')
        ->and($inner)->toContain('fn.section')
        ->and($inner)->toContain('isActive(fn)');

    $outside = str_replace($loops[0][0], '', $tpl);

    expect($outside)->not->toMatch('/\bfn\./')
        ->and($outside)->not->toMatch('/\(fn\)/');
});
