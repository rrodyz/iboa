<?php

namespace App\Console\Commands;

use App\Models\ProductStock;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Reconstitue un mouvement « entree » initial pour chaque (produit, entrepôt)
 * dont la quantité actuelle diverge du solde calculé depuis l'historique.
 *
 * Cas typique : stocks importés via seeders ou migration de données sans
 * mouvement de traçabilité associé. Cette commande génère un mouvement
 * `entree` rétroactif avec :
 *   - quantity   = écart (stocké − calculé)
 *   - type       = 'entree'
 *   - reference  = 'STOCK INITIAL'
 *   - occurred_at = date la plus ancienne possible (created_at du ProductStock)
 *
 * Le `quantity` du ProductStock n'est PAS modifié — on rétablit juste
 * la cohérence historique. Le résultat de `stock:reconcile` devient 0 écart.
 *
 * Usage :
 *   php artisan stock:reconstitute-initial --dry-run  → simule sans écrire
 *   php artisan stock:reconstitute-initial            → applique réellement
 */
class ReconstituteInitialStock extends Command
{
    protected $signature   = 'stock:reconstitute-initial
                              {--dry-run : Simule sans écrire en base}
                              {--label=Stock initial : Libellé du mouvement de reconstitution}';
    protected $description = 'Crée des mouvements "entree" initiaux pour les stocks importés sans historique';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $label  = $this->option('label');

        $stocks = ProductStock::with('product:id,reference,name', 'warehouse:id,code')->get();
        if ($stocks->isEmpty()) {
            $this->info('Aucun ProductStock — rien à reconstituer.');
            return self::SUCCESS;
        }

        $this->info("📦 Analyse de {$stocks->count()} lignes de stock" . ($dryRun ? ' (DRY-RUN)' : '') . "\n");

        $created  = 0;
        $skipped  = 0;
        $errors   = 0;

        foreach ($stocks as $ps) {
            $entrees = (float) StockMovement::where('product_id', $ps->product_id)
                ->where('warehouse_id', $ps->warehouse_id)
                ->whereIn('type', ['entree', 'reception', 'retour_client', 'transfert_in'])
                ->sum('quantity');
            $sorties = (float) StockMovement::where('product_id', $ps->product_id)
                ->where('warehouse_id', $ps->warehouse_id)
                ->whereIn('type', ['sortie', 'livraison', 'retour_fournisseur', 'transfert'])
                ->sum('quantity');
            $ajusts = (float) StockMovement::where('product_id', $ps->product_id)
                ->where('warehouse_id', $ps->warehouse_id)
                ->where('type', 'ajustement')
                ->sum('quantity');
            $calc = $entrees - $sorties + $ajusts;
            $diff = round((float) $ps->quantity - $calc, 4);

            if (abs($diff) < 0.01) {
                $skipped++;
                continue;
            }

            // Empêche les écarts négatifs (mouvements > stock physique) — on ne génère pas de sortie fantôme
            if ($diff < 0) {
                $this->warn("  ⚠  " . ($ps->product?->reference ?? '#'.$ps->product_id) . " : écart négatif ({$diff}) — saute (à corriger manuellement)");
                $errors++;
                continue;
            }

            $unitCost = (float) ($ps->avg_cost ?? $ps->product?->purchase_price ?? 0);

            try {
                if (!$dryRun) {
                    DB::transaction(function () use ($ps, $diff, $label, $unitCost) {
                        StockMovement::create([
                            'product_id'       => $ps->product_id,
                            'warehouse_id'     => $ps->warehouse_id,
                            'type'             => 'entree',
                            'quantity'         => $diff,
                            'unit_cost'        => $unitCost,
                            'total_cost'       => $diff * $unitCost,
                            'avg_cost_after'   => $unitCost,
                            'valuation_method' => $ps->product?->valuation_method ?? 'cmp',
                            'reference_type'   => 'initial_reconstitution',
                            'reference_id'    => $ps->id,
                            'notes'            => $label . ' — reconstitution rétroactive',
                            'occurred_at'      => $ps->created_at ?? now(),
                            'created_by'       => Auth::id(),
                        ]);
                    });
                }

                $this->line(sprintf(
                    "  %s  %s / %s : +%s unités → mouvement %s",
                    $dryRun ? '[DRY]' : '✓',
                    $ps->product?->reference ?? '#'.$ps->product_id,
                    $ps->warehouse?->code ?? '#'.$ps->warehouse_id,
                    number_format($diff, 2, ',', ' '),
                    $dryRun ? 'simulé' : 'créé'
                ));
                $created++;

            } catch (\Throwable $e) {
                $errors++;
                $this->error("  ✗ " . ($ps->product?->reference ?? '#'.$ps->product_id) . " : " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("✅ Total : {$created} reconstitué(s), {$skipped} déjà cohérent(s), {$errors} en erreur");

        if (!$dryRun && $created > 0) {
            $this->line("\nLancez maintenant `php artisan stock:reconcile` pour vérifier.");
        }

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
