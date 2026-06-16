<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\BillOfMaterial;

/**
 * [PRODUCTION] Explosion multi-niveaux d'une nomenclature.
 *
 * Parcourt récursivement les composants : si un composant est un produit
 * SEMI-FINI possédant sa propre nomenclature active, il est explosé à son tour.
 * Sert aux assemblages (charpentes, hangars) à plusieurs niveaux de fabrication.
 */
class BomExplosionService
{
    private const MAX_DEPTH = 8;

    /**
     * @return array<int, array{product_id:?int,label:string,quantity:float,is_semi_finished:bool,has_sub_bom:bool,depth:int}>
     */
    public function explode(BillOfMaterial $bom, float $quantity = 1, int $depth = 0): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return [];
        }

        $bom->loadMissing('lines.product');
        $rows = [];

        foreach ($bom->lines as $line) {
            $qty = (float) $line->quantity_per_meter * $quantity;
            $product = $line->product;
            $isSf = (bool) ($product?->is_semi_finished);

            // Sous-nomenclature active du composant semi-fini
            $subBom = $isSf && $product
                ? BillOfMaterial::where('product_id', $product->id)->where('is_active', true)->with('lines.product')->first()
                : null;

            $rows[] = [
                'product_id'       => $product?->id,
                'label'            => $line->label ?? $product?->name ?? 'Composant',
                'quantity'         => round($qty, 4),
                'is_semi_finished' => $isSf,
                'has_sub_bom'      => (bool) $subBom,
                'depth'            => $depth,
            ];

            if ($subBom) {
                $rows = array_merge($rows, $this->explode($subBom, $qty, $depth + 1));
            }
        }

        return $rows;
    }
}
