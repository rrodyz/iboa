<?php

namespace App\Support\Pdf;

use Barryvdh\DomPDF\PDF;

/**
 * [P6 — Phase 15] CSS `counter(pages)` ne se résout pas dans ce build dompdf :
 * reste à 0 quelle que soit la syntaxe (élément classique testé et confirmé
 * cassé ; règle `@page { @bottom-right { ... } }` testée et confirmée non
 * supportée proprement — les déclarations fuitent sur le contenu du body
 * au lieu d'être cantonnées à la marge de page). Seule l'API bas niveau
 * `Canvas::page_text()`, appelée après le rendu complet (le nombre réel de
 * pages est alors connu), produit un total correct — vérifié visuellement :
 * "Page 1 / 3", "Page 2 / 3", "Page 3 / 3" sur un document 3 pages.
 */
class PageNumberStamp
{
    /**
     * Appelle $pdf->render() (idempotent — output()/stream()/download()
     * ne re-rendront pas après) puis grave le numéro de page en bas à
     * droite, à appeler juste avant stream()/download()/output().
     */
    public static function apply(
        PDF $pdf,
        float $rightMargin = 28.0,
        float $bottomMargin = 4.0,
        float $fontSize = 7.5,
        string $fontFamily = 'DejaVu Sans',
        array $color = [0.61, 0.64, 0.69], // #9ca3af
    ): void {
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = $metrics->getFont($fontFamily, 'normal');
        $text = 'Page {PAGE_NUM} / {PAGE_COUNT}';

        // Largeur calculée sur le texte à substituer (placeholders inclus,
        // donc légèrement plus large que le texte final réel) — marge
        // droite finale toujours >= $rightMargin, jamais de débordement.
        $width = $metrics->getTextWidth($text, $font, $fontSize);
        $x = $canvas->get_width() - $rightMargin - $width;
        $y = $canvas->get_height() - $bottomMargin - $fontSize;

        $canvas->page_text($x, $y, $text, $font, $fontSize, $color);
    }
}
