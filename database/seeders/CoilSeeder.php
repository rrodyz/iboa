<?php

namespace Database\Seeders;

use App\Modules\Production\Models\Coil;
use App\Models\Company;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class CoilSeeder extends Seeder
{
    public const COLORS     = ['Rouge', 'Bleu', 'Vert', 'Gris', 'Blanc', 'Noir', 'Marron'];
    public const THICKNESS  = [0.25, 0.30, 0.35, 0.40, 0.45];
    public const WIDTHS     = [1000, 1200, 1250];

    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            return;
        }

        if (Coil::where('company_id', $company->id)->count() >= 50) {
            $this->command?->info('  ✓ Bobines déjà présentes (≥50)');

            return;
        }

        $suppliers = Supplier::pluck('id')->all();
        $matiere   = Product::query()->where('is_stockable', true)->value('id');

        for ($i = 1; $i <= 50; $i++) {
            $weight    = random_int(800, 3000);
            $pricePerKg = random_int(450, 950);
            $price     = $weight * $pricePerKg;

            // Statut + poids restant cohérents
            $status = match (true) {
                $i % 7 === 0 => 'epuisee',
                $i % 3 === 0 => 'en_production',
                default      => 'disponible',
            };
            $remaining = match ($status) {
                'epuisee'       => 0,
                'en_production' => round($weight * (random_int(20, 70) / 100), 2),
                default         => $weight,
            };

            Coil::create([
                'company_id'       => $company->id,
                'product_id'       => $matiere,
                'supplier_id'      => $suppliers ? $suppliers[array_rand($suppliers)] : null,
                'reference'        => 'BOB-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'lot_number'       => 'LOT-' . now()->format('y') . '-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'color'            => self::COLORS[array_rand(self::COLORS)],
                'thickness'        => self::THICKNESS[array_rand(self::THICKNESS)],
                'width'            => self::WIDTHS[array_rand(self::WIDTHS)],
                'initial_weight'   => $weight,
                'remaining_weight' => $remaining,
                'estimated_length' => round($weight / 4, 2),
                'purchase_price'   => $price,
                'cost_per_kg'      => round($price / $weight, 2),
                'received_at'      => now()->subDays(random_int(0, 120)),
                'status'           => $status,
            ]);
        }

        $this->command?->info('  ✓ 50 bobines');
    }
}
