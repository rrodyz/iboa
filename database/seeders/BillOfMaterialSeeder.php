<?php

namespace Database\Seeders;

use App\Modules\Production\Models\BillOfMaterial;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Seeder;

class BillOfMaterialSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            return;
        }

        // 10 combinaisons couleur × épaisseur
        $combos = [
            ['Rouge', 0.25], ['Bleu', 0.30], ['Gris', 0.35], ['Blanc', 0.40], ['Vert', 0.45],
            ['Noir', 0.25], ['Marron', 0.30], ['Rouge', 0.35], ['Bleu', 0.40], ['Gris', 0.45],
        ];

        foreach ($combos as $i => [$color, $thickness]) {
            $name = "Tôle bac {$color} {$thickness} mm";

            // Produit fini associé (réutilise products existants)
            $product = Product::firstOrCreate(
                ['reference' => 'TB-' . strtoupper(substr($color, 0, 3)) . '-' . str_replace('.', '', (string) $thickness)],
                [
                    'name'             => $name,
                    'type'             => 'simple',
                    'is_stockable'     => true,
                    'is_sellable'      => true,
                    'is_purchasable'   => false,
                    'purchase_price'   => 0,
                    'sale_price'       => random_int(3_500, 6_500),
                    'stock_min'        => 0,
                    'valuation_method' => 'cmp',
                    'is_active'        => true,
                ],
            );

            BillOfMaterial::firstOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                [
                    'company_id'            => $company->id,
                    'product_id'            => $product->id,
                    'sheet_type'            => 'bac',
                    'thickness'             => $thickness,
                    'coil_width'            => 1200,
                    'usable_width'          => 1000,
                    'standard_waste_rate'   => round(2 + $i * 0.4, 2),
                    'consumption_per_meter' => round($thickness * 8, 4),   // ~kg/m proportionnel épaisseur
                    'machine_time_per_unit' => round(1 + $thickness * 4, 2),
                    'labor_per_unit'        => 100 + $i * 20,
                    'is_active'             => true,
                ],
            );
        }

        $this->command?->info('  ✓ 10 nomenclatures + produits finis');
    }
}
