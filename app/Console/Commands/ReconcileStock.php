<?php

namespace App\Console\Commands;

use App\Models\ProductStock;
use App\Models\StockMovement;
use Illuminate\Console\Command;

/**
 * Vérifie la cohérence entre les `product_stocks` (état actuel) et les
 * `stock_movements` (historique) sans modifier les données.
 *
 * Pour chaque (produit, entrepôt) :
 *   solde_calculé = Σ(entrées + ajustements positifs) − Σ(sorties + ajustements négatifs)
 *
 * Sortie : tableau récapitulatif des incohérences avec écart en valeur absolue.
 * Code de retour 0 si tout OK, 1 si écarts détectés.
 *
 * Usage :
 *   php artisan stock:reconcile           → vérifie tout
 *   php artisan stock:reconcile -v        → affiche aussi les OK
 */
class ReconcileStock extends Command
{
    protected $signature   = 'stock:reconcile {--show-all : Affiche également les stocks cohérents}';
    protected $description = 'Vérifie la cohérence entre product_stocks et stock_movements (lecture seule)';

    public function handle(): int
    {
        $stocks = ProductStock::with(['product:id,reference,name', 'warehouse:id,code,name'])->get();
        if ($stocks->isEmpty()) {
            $this->info('Aucun ProductStock en base.');
            return self::SUCCESS;
        }

        $headers = ['Article', 'Entrepôt', 'Stocké', 'Calculé', 'Écart', 'Verdict'];
        $rows    = [];
        $bugs    = 0;

        foreach ($stocks as $ps) {
            $calc = $this->calculatedBalance($ps->product_id, $ps->warehouse_id);
            $diff = round((float) $ps->quantity - $calc, 4);

            $verdict = abs($diff) < 0.01 ? '✓ OK' : '✗ ÉCART';
            if ($verdict === '✗ ÉCART') {
                $bugs++;
            }

            if ($verdict !== '✓ OK' || $this->option('show-all')) {
                $rows[] = [
                    ($ps->product?->reference ?? '#'.$ps->product_id) . ' — ' . substr($ps->product?->name ?? '?', 0, 30),
                    $ps->warehouse?->code ?? '#'.$ps->warehouse_id,
                    number_format($ps->quantity, 2, ',', ' '),
                    number_format($calc, 2, ',', ' '),
                    number_format($diff, 2, ',', ' '),
                    $verdict,
                ];
            }
        }

        if (empty($rows)) {
            $this->info('✓ Tous les stocks sont cohérents (' . $stocks->count() . ' lignes vérifiées).');
            return self::SUCCESS;
        }

        $this->table($headers, $rows);

        if ($bugs > 0) {
            $this->warn("⚠  {$bugs} incohérence(s) détectée(s) sur " . $stocks->count() . " lignes.");
            $this->line("    → Pour reconstituer un état initial : php artisan stock:reconstitute-initial");
            return self::FAILURE;
        }

        $this->info("✓ Aucune incohérence — affichage verbeux de {$stocks->count()} lignes OK.");
        return self::SUCCESS;
    }

    /**
     * Calcule le solde théorique d'un (produit, entrepôt) depuis l'historique des mouvements.
     * Respecte la convention de signe : entrée = +qty, sortie = -qty, ajustement = qty signée.
     */
    private function calculatedBalance(int $productId, int $warehouseId): float
    {
        $entrees = (float) StockMovement::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->whereIn('type', ['entree', 'reception', 'retour_client', 'transfert_in'])
            ->sum('quantity');

        $sorties = (float) StockMovement::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->whereIn('type', ['sortie', 'livraison', 'retour_fournisseur', 'transfert'])
            ->sum('quantity');

        // Ajustement : qty signée (positive = entrée, négative = sortie)
        $ajusts = (float) StockMovement::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('type', 'ajustement')
            ->sum('quantity');

        return $entrees - $sorties + $ajusts;
    }
}
