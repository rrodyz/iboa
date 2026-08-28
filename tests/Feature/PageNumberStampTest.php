<?php

use App\Support\Pdf\PageNumberStamp;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * [P6 — Phase 15] Régression ciblée sur la faille « Page X / 0 » (dompdf ne
 * résout pas counter(pages) en un seul passage). Ce test n'inspecte pas le
 * flux PDF (police subsettée -> glyphes indexés, pas de texte brut
 * extractible sans dépendance de parsing PDF supplémentaire, jugée
 * disproportionnée pour cette seule assertion et potentiellement fragile).
 * Il exerce la mécanique réelle : rendu dompdf réel, comptage de pages
 * réel, appel réel à Canvas::page_text() — la preuve visuelle (PDF de
 * facture réel, 1/3, 2/3, 3/3 confirmés) complète ce test, cf. rapport P6.
 */
function multiPageHtml(int $breaks): string
{
    $body = '<p>Page 1</p>';
    for ($i = 0; $i < $breaks; $i++) {
        $body .= '<div style="page-break-after: always;"></div><p>Page '.($i + 2).'</p>';
    }

    return '<html><body>'.$body.'</body></html>';
}

it('ne lève aucune exception et compte correctement les pages sur un document 1 page', function () {
    $pdf = Pdf::loadHTML(multiPageHtml(0));

    PageNumberStamp::apply($pdf);

    $canvas = $pdf->getDomPDF()->getCanvas();
    expect($canvas->get_page_count())->toBe(1);

    $bytes = $pdf->output();
    expect($bytes)->not->toBeEmpty();
    expect(str_starts_with($bytes, '%PDF'))->toBeTrue();
});

it('ne lève aucune exception et compte correctement les pages sur un document 3 pages', function () {
    $pdf = Pdf::loadHTML(multiPageHtml(2));

    PageNumberStamp::apply($pdf);

    $canvas = $pdf->getDomPDF()->getCanvas();
    expect($canvas->get_page_count())->toBe(3);

    $bytes = $pdf->output();
    expect($bytes)->not->toBeEmpty();
    expect(str_starts_with($bytes, '%PDF'))->toBeTrue();
});

it('accepte un appel après render() déjà effectué sans re-render ni erreur (idempotence output)', function () {
    $pdf = Pdf::loadHTML(multiPageHtml(1));
    $pdf->render();

    PageNumberStamp::apply($pdf);

    // output() ne doit pas re-render (Barryvdh\DomPDF\PDF::rendered déjà true) —
    // le tampon posé par apply() doit survivre au output() final.
    $bytes = $pdf->output();
    expect($bytes)->not->toBeEmpty();
});
