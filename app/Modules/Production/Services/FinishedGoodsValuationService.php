<?php

namespace App\Modules\Production\Services;

use App\Models\AccountingPeriodLock;
use App\Models\FiscalYear;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\StockValuationAdjustment;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinishedGoodsValuationService
{
    public function revalue(ProductionOrder $order): void
    {
        $order->loadMissing(['cost', 'outputs.stockMovement', 'product']);
        $totalCost = (float) ($order->cost?->total_cost ?? 0);
        $totalQuantity = (float) $order->outputs->sum('quantity');

        if ($totalCost <= 0 || $totalQuantity <= 0) {
            return;
        }
        if (FiscalYear::find($order->fiscal_year_id)?->status !== 'ouvert'
            || AccountingPeriodLock::findForDate((int) $order->company_id, now())) {
            throw ValidationException::withMessages([
                'valuation' => 'Régularisation impossible : la période comptable est fermée ou verrouillée.',
            ]);
        }

        DB::transaction(function () use ($order, $totalCost, $totalQuantity) {
            foreach ($order->outputs as $output) {
                $quantity = (float) $output->quantity;
                $original = $output->stockMovement;
                if ($quantity <= 0 || ! $original) {
                    continue;
                }
                if (StockValuationAdjustment::where('production_order_id', $order->id)
                    ->where('original_movement_id', $original->id)->exists()) {
                    continue;
                }

                $allocatedCost = round($totalCost * ($quantity / $totalQuantity), 2);
                $newUnitCost = round($allocatedCost / $quantity, 2);
                $delta = round($allocatedCost - (float) $original->total_cost, 2);
                if (abs($delta) <= 0.001) {
                    continue;
                }

                $warehouseId = $output->quality_released_at && $output->release_warehouse_id
                    ? (int) $output->release_warehouse_id
                    : (int) $output->warehouse_id;
                $stock = ProductStock::where('product_id', $output->product_id)
                    ->where('warehouse_id', $warehouseId)->lockForUpdate()->first();

                // [P7.2 — clôture tardive après livraison] Un OF clôturé après que
                // son PF a été partiellement ou totalement expédié ne doit ni
                // bloquer la clôture, ni recréer du stock, ni toucher la sortie
                // déjà passée. On ne régularise le CMP QUE sur la quantité
                // physiquement encore en stock (« available ») — proportionnelle
                // au delta total. La part déjà expédiée n'est structurellement
                // plus valorisable en stock (rien à moyenner dedans) : son écart
                // de coût reste tracé intégralement dans StockValuationAdjustment
                // (audit/traçabilité — value_delta = écart complet calculé), sans
                // jamais être appliqué une seconde fois au mouvement de sortie
                // historique ni à un compte comptable qui n'existe pas dans ce
                // modèle. Ancien comportement (ValidationException bloquante)
                // retiré : le coût réel de l'OF reste de toute façon disponible
                // dans production_costs, clôture non impactée.
                $available = $stock ? min((float) $stock->quantity, $quantity) : 0.0;
                $ratio = $available > 0 ? $available / $quantity : 0.0;
                $stockDelta = round($delta * $ratio, 2);

                $newAverage = (float) ($stock?->avg_cost ?? 0);
                if ($stock && $available > 0 && abs($stockDelta) > 0.001) {
                    $stockValue = (float) $stock->quantity * (float) $stock->avg_cost;
                    $newAverage = round(($stockValue + $stockDelta) / (float) $stock->quantity, 2);
                    $stock->update(['avg_cost' => $newAverage]);
                }

                $adjustmentMovement = StockMovement::create([
                    'product_id' => $output->product_id,
                    'warehouse_id' => $warehouseId,
                    'type' => 'valuation_adjustment',
                    'quantity' => 0,
                    'unit_cost' => $available > 0 ? round($stockDelta / $available, 2) : 0,
                    'total_cost' => $stockDelta,
                    'valuation_method' => $order->product?->valuation_method ?? 'cmp',
                    'avg_cost_after' => $newAverage,
                    'occurred_at' => now(),
                    'reference_type' => ProductionOrder::class,
                    'reference_id' => $order->id,
                    'idempotency_key' => 'production-valuation-adjustment:'.$order->id.':'.$output->id,
                    'notes' => $available >= $quantity
                        ? 'Régularisation du coût provisoire vers le coût complet de l’OF '.$order->number
                        : sprintf(
                            'Régularisation partielle du coût provisoire de l’OF %s : %s/%s unité(s) encore en stock (le solde a déjà été livré/consommé — écart complet tracé en audit, non ré-appliqué à la sortie historique).',
                            $order->number,
                            rtrim(rtrim(number_format($available, 2, ',', ' '), '0'), ','),
                            rtrim(rtrim(number_format($quantity, 2, ',', ' '), '0'), ',')
                        ),
                    'created_by' => Auth::id(),
                ]);

                StockValuationAdjustment::create([
                    'company_id' => $order->company_id,
                    'production_order_id' => $order->id,
                    'original_movement_id' => $original->id,
                    'adjustment_movement_id' => $adjustmentMovement->id,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $quantity,
                    'old_unit_cost' => (float) $original->unit_cost,
                    'new_unit_cost' => $newUnitCost,
                    'value_delta' => $delta,
                    'reason' => $available >= $quantity
                        ? 'Passage du coût provisoire au coût complet à la clôture de l’OF'
                        : 'Passage du coût provisoire au coût complet à la clôture de l’OF (écart total tracé ici ; seule la quote-part encore en stock a été appliquée au CMP — voir mouvement de régularisation lié)',
                    'created_by' => Auth::id(),
                ]);
            }

            $weighted = ProductStock::where('product_id', $order->product_id)
                ->where('quantity', '>', 0)
                ->selectRaw('SUM(quantity * avg_cost) / NULLIF(SUM(quantity), 0) AS value')
                ->value('value');
            if ($weighted !== null) {
                $order->product?->updateQuietly(['weighted_avg_cost' => round((float) $weighted, 2)]);
            }
        });
    }
}
