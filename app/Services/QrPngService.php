<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * [PDF] Génère un QR code en PNG (GD) plutôt qu'en SVG.
 *
 * DomPDF rend mal les <img src="data:image/svg+xml…"> : la vectorisation du QR
 * gonfle le fichier (~1.3 Mo) et certains lecteurs (pdftoppm/Acrobat) interprètent
 * la page surdimensionnée comme une dizaine de pages blanches fantômes.
 * Un PNG matriciel est rendu nativement et proprement par DomPDF.
 */
class QrPngService
{
    /**
     * Retourne un data URI PNG du QR, ou null en cas d'échec (le PDF reste valide).
     *
     * @param  string  $text       Contenu encodé
     * @param  int     $moduleSize  Taille d'un module en pixels
     * @param  int     $margin      Marge silencieuse en modules
     */
    public function dataUri(string $text, int $moduleSize = 4, int $margin = 4): ?string
    {
        if ($text === '' || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        try {
            $matrix = Encoder::encode($text, ErrorCorrectionLevel::M())->getMatrix();
            $w = $matrix->getWidth();
            $h = $matrix->getHeight();
            if ($w === 0 || $h === 0) {
                return null;
            }

            $px = ($w + 2 * $margin) * $moduleSize;
            $py = ($h + 2 * $margin) * $moduleSize;

            $img   = imagecreatetruecolor($px, $py);
            $white = imagecolorallocate($img, 255, 255, 255);
            $black = imagecolorallocate($img, 0, 0, 0);
            imagefilledrectangle($img, 0, 0, $px, $py, $white);

            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    if ($matrix->get($x, $y)) {
                        $x0 = ($x + $margin) * $moduleSize;
                        $y0 = ($y + $margin) * $moduleSize;
                        imagefilledrectangle($img, $x0, $y0, $x0 + $moduleSize - 1, $y0 + $moduleSize - 1, $black);
                    }
                }
            }

            ob_start();
            imagepng($img);
            $png = ob_get_clean();
            imagedestroy($img);

            return 'data:image/png;base64,' . base64_encode($png);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }
}
