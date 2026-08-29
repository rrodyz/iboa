<?php

namespace App\Modules\Production\Services;

use App\Models\Product;
use App\Models\ProductStock;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * [MRP V2 — PROD-01 Phase 1/2/3] Besoin net matière première par explosion
 * de nomenclature multi-niveaux, avec traçabilité (pegging) vers la source
 * du besoin (commande client MTO, cible MTS).
 *
 * Réutilise BomExplosionService (explosion récursive) — jamais de seconde
 * implémentation de la récursion nomenclature — et la même règle de besoin
 * net que NetRequirementService (dispo, OF planifiés, réceptions
 * attendues), appliquée cette fois aux matières premières explosées plutôt
 * qu'à l'article fini/semi-fini lui-même.
 */
class MrpPeggingService
{
    private const SO_OPEN = ['confirme', 'en_preparation', 'partiellement_livre'];

    private const PO_OPEN = ['envoye', 'confirme', 'partiellement_recu'];

    public function __construct(
        private BomExplosionService $explosion,
        private NetRequirementService $netRequirement,
    ) {}

    /**
     * Besoin net par matière première, tous les articles fabriqués
     * (MTO ouverts + propositions MTS) explosés et agrégés.
     *
     * @return Collection<int, array<string,mixed>>
     */
    public function explodedNetRequirements(): Collection
    {
        $rows = $this->grossDemandRows();

        if ($rows->isEmpty()) {
            return collect();
        }

        $ids = $rows->pluck('product_id')->unique()->filter();
        $products = Product::whereIn('id', $ids)->get()->keyBy('id');

        $stocks = ProductStock::whereIn('product_id', $ids)
            ->selectRaw('product_id, SUM(quantity) qty, SUM(reserved_quantity) reserved')
            ->groupBy('product_id')->get()->keyBy('product_id');

        $planned = ProductionOrder::whereIn('product_id', $ids)
            ->whereNotIn('status', ['termine', 'annule'])
            ->selectRaw('product_id, SUM(quantity_requested) qty')
            ->groupBy('product_id')->pluck('qty', 'product_id');

        $attendu = DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->whereNull('po.deleted_at')
            ->whereIn('po.status', self::PO_OPEN)
            ->whereIn('poi.product_id', $ids)
            ->whereColumn('poi.received_quantity', '<', 'poi.quantity')
            ->selectRaw('poi.product_id AS pid, SUM(poi.quantity - poi.received_quantity) AS qte, MIN(po.expected_at) AS date_attendue')
            ->groupBy('poi.product_id')->get()->keyBy('pid');

        return $rows->groupBy('product_id')->map(function (Collection $group, $productId) use ($stocks, $planned, $attendu, $products) {
            $product = $products[$productId] ?? null;
            $besoinBrut = round($group->sum('quantity'), 4);

            $physique = (float) ($stocks[$productId]->qty ?? 0);
            $reserve = (float) ($stocks[$productId]->reserved ?? 0);
            $dispo = $physique - $reserve;
            $plan = (float) ($planned[$productId] ?? 0);
            $recu = (float) ($attendu[$productId]->qte ?? 0);
            $dateAttendue = $attendu[$productId]->date_attendue ?? null;

            $besoinNet = max(0.0, round($besoinBrut - $dispo - $plan - $recu, 4));

            // [PROD-01 Phase 5] Phasage temporel : une réception attendue APRÈS
            // le besoin le plus proche ne doit jamais l'éteindre silencieusement
            // — le besoin_net global reste correct au global, mais l'échéance la
            // plus proche doit rester visible comme non couverte À TEMPS.
            $needDate = $group->pluck('need_date')->filter()->map(fn ($d) => \Illuminate\Support\Carbon::parse($d))->sort()->first();
            $lateSupply = $needDate && $dateAttendue && \Illuminate\Support\Carbon::parse($dateAttendue)->gt($needDate);
            $recuATemps = $lateSupply ? 0.0 : $recu;
            $besoinNetATemps = max(0.0, round($besoinBrut - $dispo - $plan - $recuATemps, 4));

            return [
                'product' => $product,
                'besoin_brut' => $besoinBrut,
                'physique' => $physique,
                'reserve' => $reserve,
                'dispo' => $dispo,
                'plan' => $plan,
                'recu' => $recu,
                'date_attendue' => $dateAttendue,
                'besoin_net' => $besoinNet,
                'need_date' => $needDate,
                'late_supply' => (bool) $lateSupply,
                'besoin_net_a_temps' => $besoinNetATemps,
                'sources' => $group->map(fn ($r) => [
                    'source_type' => $r['source_type'], 'source_id' => $r['source_id'],
                    'source_label' => $r['source_label'], 'quantity' => $r['quantity'],
                    'need_date' => $r['need_date'] ?? null, 'depth' => $r['depth'],
                ])->values(),
            ];
        })->values();
    }

    /**
     * Lignes de besoin brut, avant confrontation au stock : une par
     * (matière première, source). Une même matière peut apparaître
     * plusieurs fois (plusieurs commandes/propositions qui la consomment) —
     * c'est le détail que explodedNetRequirements() agrège et que le
     * pegging conserve intact pour la remontée vers la source.
     *
     * @return Collection<int, array<string,mixed>>
     */
    public function grossDemandRows(): Collection
    {
        $rows = collect();

        foreach ($this->mtoTopDemands() as $d) {
            $rows = $rows->merge($this->explodeOne($d));
        }
        foreach ($this->mtsTopDemands() as $d) {
            $rows = $rows->merge($this->explodeOne($d));
        }

        return $rows->filter(fn ($r) => $r['product_id'] !== null)->values();
    }

    /** @return array<int,array{product:?Product,quantity:float,source_type:string,source_id:int,source_label:string,need_date:?string}> */
    private function mtoTopDemands(): array
    {
        $items = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('products as p', 'p.id', '=', 'oi.product_id')
            ->whereNull('o.deleted_at')
            ->whereIn('o.status', self::SO_OPEN)
            ->where('p.production_mode', 'mto')
            ->whereColumn('oi.delivered_quantity', '<', 'oi.quantity')
            ->select('oi.product_id', 'oi.quantity', 'oi.delivered_quantity', 'o.id as order_id', 'o.number', 'o.delivery_date')
            ->get();

        $products = Product::whereIn('id', $items->pluck('product_id')->unique())->get()->keyBy('id');

        return $items->map(fn ($i) => [
            'product' => $products[$i->product_id] ?? null,
            'quantity' => (float) $i->quantity - (float) $i->delivered_quantity,
            'source_type' => 'commande', 'source_id' => $i->order_id,
            'source_label' => $i->number, 'need_date' => $i->delivery_date,
        ])->filter(fn ($d) => $d['product'] && $d['quantity'] > 0)->all();
    }

    /** @return array<int,array{product:Product,quantity:float,source_type:string,source_id:int,source_label:string,need_date:?string}> */
    private function mtsTopDemands(): array
    {
        return $this->netRequirement->proposals()->map(fn ($r) => [
            'product' => $r['p'], 'quantity' => $r['besoin'],
            'source_type' => 'mts', 'source_id' => $r['p']->id,
            'source_label' => 'Cible MTS — '.$r['p']->name, 'need_date' => null,
        ])->all();
    }

    /** @return array<int,array<string,mixed>> */
    private function explodeOne(array $demand): array
    {
        $product = $demand['product'];
        $bom = BillOfMaterial::where('product_id', $product->id)->where('is_active', true)->first();
        if (! $bom || $demand['quantity'] <= 0) {
            return [];
        }

        return array_map(fn (array $row) => $row + [
            'source_type' => $demand['source_type'], 'source_id' => $demand['source_id'],
            'source_label' => $demand['source_label'], 'need_date' => $demand['need_date'],
        ], $this->explosion->explode($bom, $demand['quantity']));
    }
}
