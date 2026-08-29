<?php

namespace App\Modules\Production\Services;

use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * [PROD-01 — Phase 14] Propositions de transfert inter-dépôts.
 *
 * Un dépôt en EXCÉDENT (stock disponible au-delà de sa propre demande client
 * ferme locale) est proposé en source vers un dépôt en BESOIN (demande
 * locale non couverte par son propre stock). Ne propose jamais plus que ce
 * qui est réellement disponible à la source — l'appariement décrémente les
 * deux pools au fur et à mesure, jamais un transfert en double emploi du
 * même excédent.
 *
 * Distinct du MRP (NetRequirementService) qui raisonne au global toutes
 * dépôts confondus : ici le déséquilibre est PAR DÉPÔT, une redistribution,
 * pas une nouvelle fabrication/un nouvel achat.
 */
class TransferProposalService
{
    private const SO_OPEN = ['confirme', 'en_preparation', 'partiellement_livre'];

    /** @return Collection<int, array<string,mixed>> */
    public function proposals(): Collection
    {
        $stocks = ProductStock::where('quantity', '>', 0)
            ->with('warehouse:id,name')
            ->get(['id', 'product_id', 'warehouse_id', 'quantity', 'reserved_quantity']);

        if ($stocks->isEmpty()) {
            return collect();
        }

        $productIds = $stocks->pluck('product_id')->unique();
        $demandByProductWarehouse = $this->localFirmDemand($productIds);
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $proposals = collect();

        foreach ($stocks->groupBy('product_id') as $productId => $group) {
            $product = $products[$productId] ?? null;
            if (! $product) {
                continue;
            }

            $surplus = collect();
            $shortage = collect();

            foreach ($group as $stock) {
                $dispo = (float) $stock->quantity - (float) $stock->reserved_quantity;
                $localDemand = (float) ($demandByProductWarehouse[$productId.'|'.$stock->warehouse_id] ?? 0);
                $delta = round($dispo - $localDemand, 4);

                if ($delta > 0.0001) {
                    $surplus->push(['warehouse_id' => $stock->warehouse_id, 'warehouse' => $stock->warehouse, 'qty' => $delta]);
                } elseif ($delta < -0.0001) {
                    $shortage->push(['warehouse_id' => $stock->warehouse_id, 'warehouse' => $stock->warehouse, 'qty' => -$delta]);
                }
            }

            // Une demande locale sur un dépôt sans AUCUNE ligne de stock
            // (jamais approvisionné) est aussi un besoin de transfert.
            foreach ($demandByProductWarehouse as $key => $qty) {
                [$pid, $whId] = explode('|', $key);
                if ((int) $pid !== $productId || $group->firstWhere('warehouse_id', (int) $whId)) {
                    continue;
                }
                $shortage->push(['warehouse_id' => (int) $whId, 'warehouse' => \App\Models\Warehouse::find((int) $whId), 'qty' => (float) $qty]);
            }

            if ($surplus->isEmpty() || $shortage->isEmpty()) {
                continue;
            }

            $proposals = $proposals->merge($this->match($product, $surplus->sortByDesc('qty')->values(), $shortage->sortByDesc('qty')->values()));
        }

        return $proposals->values();
    }

    /** @return Collection<int, array<string,mixed>> */
    private function match(Product $product, Collection $surplus, Collection $shortage): Collection
    {
        $rows = collect();
        $s = $surplus->all();
        $d = $shortage->all();

        foreach ($s as $i => &$from) {
            foreach ($d as $j => &$to) {
                if ($from['qty'] <= 0.0001 || $to['qty'] <= 0.0001 || $from['warehouse_id'] === $to['warehouse_id']) {
                    continue;
                }

                $qty = round(min($from['qty'], $to['qty']), 4);
                if ($qty <= 0.0001) {
                    continue;
                }

                $rows->push([
                    'product' => $product,
                    'from_warehouse' => $from['warehouse'],
                    'to_warehouse' => $to['warehouse'],
                    'quantity' => $qty,
                ]);

                $from['qty'] -= $qty;
                $to['qty'] -= $qty;
            }
        }

        return $rows;
    }

    /** @return array<string,float> clé "product_id|warehouse_id" */
    private function localFirmDemand(Collection $productIds): array
    {
        return DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->whereNull('o.deleted_at')
            ->whereIn('o.status', self::SO_OPEN)
            ->whereIn('oi.product_id', $productIds)
            ->whereColumn('oi.delivered_quantity', '<', 'oi.quantity')
            ->whereRaw('COALESCE(oi.warehouse_id, o.delivery_warehouse_id) IS NOT NULL')
            ->selectRaw('oi.product_id AS pid, COALESCE(oi.warehouse_id, o.delivery_warehouse_id) AS wid, SUM(oi.quantity - oi.delivered_quantity) AS qte')
            ->groupBy('pid', 'wid')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->pid.'|'.$r->wid => (float) $r->qte])
            ->all();
    }
}
