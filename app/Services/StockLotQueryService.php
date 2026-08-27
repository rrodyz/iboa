<?php

namespace App\Services;

use App\Models\StockLot;
use App\Models\StockReservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * [P1-E] Read model de consultation « Stock par lot ». Pure lecture — aucune
 * écriture, aucune règle métier de réservation/consommation (ça reste dans
 * ReservationService/CoilConsumptionService, non touchés ici).
 *
 * Sources de vérité respectées (cf. rapport P1-E) :
 *   - PHYSIQUE  = stock_lots.quantity (jamais recalculé depuis product_stocks,
 *     qui est au niveau article+dépôt, une granularité plus grossière) ;
 *   - RÉSERVÉ   = SUM(quantity − consumed_quantity) des stock_reservations
 *     actives (status=reserved) rattachées à CE lot précis (stock_lot_id) —
 *     la même formule que StockReservation::remainingReserved(), en SQL pour
 *     éviter de charger toutes les réservations en PHP (pas de N+1) ; jamais
 *     stock_lots.reserved_quantity (colonne legacy non alimentée depuis P1-D) ;
 *   - DISPONIBLE = PHYSIQUE − RÉSERVÉ, jamais clampé à 0 : une incohérence
 *     doit rester visible, pas masquée.
 */
class StockLotQueryService
{
    /** Sous-requête corrélée : somme des réservations actives liées au lot (formule canonique P1-D3). */
    private function reservedSubquery(): \Illuminate\Database\Query\Builder
    {
        return StockReservation::query()
            ->selectRaw('COALESCE(SUM(quantity - consumed_quantity), 0)')
            ->whereColumn('stock_reservations.stock_lot_id', 'stock_lots.id')
            ->where('stock_reservations.status', 'reserved')
            ->toBase();
    }

    /** @param array{search?:string,product_id?:int,warehouse_id?:int,status?:string,quality_status?:string,availability?:string} $filters */
    public function baseQuery(array $filters): Builder
    {
        $query = StockLot::query()
            ->with(['product:id,name,reference,has_lot_number', 'warehouse:id,name,code', 'coils:id,stock_lot_id,reference'])
            // [Company isolation — audit P1-E] stock_lots n'a pas de company_id
            // propre (mono-société assumé, cf. Product). warehouse_id, lui,
            // référence un Warehouse company-scopé (HasCompanyScope) : forcer
            // l'EXISTS sur la relation applique ce scope de façon fiable, alors
            // que with('warehouse') seul laisse passer la ligne avec une
            // relation simplement vide/nulle pour une autre société — pas une
            // vraie exclusion. whereHas('product') ne suffirait pas : Product
            // n'a lui-même aucun scope société.
            ->whereHas('warehouse')
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->addSelect(['reserved_quantity' => $this->reservedSubquery()]);

        if (! empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }
        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['quality_status'])) {
            $query->where('quality_status', $filters['quality_status']);
        }
        if (($filters['availability'] ?? '') === 'with_stock') {
            $query->where('quantity', '>', 0);
        } elseif (($filters['availability'] ?? '') === 'exhausted') {
            $query->where('quantity', '<=', 0);
        }
        if (! empty($filters['search'])) {
            $s = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('lot_number', 'like', $s)
                ->orWhere('serial_number', 'like', $s)
                ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', $s)->orWhere('reference', 'like', $s)));
        }

        return $query;
    }

    /**
     * KPI sur l'ENSEMBLE du résultat filtré (jamais seulement la page paginée) —
     * même builder que paginate(), cloné AVANT tout ->paginate()/->forPage().
     *
     * @return array{lots:int,physical:float,reserved:float,available:float,value:float}
     */
    public function aggregate(Builder $query): array
    {
        $rows = (clone $query)->get(['stock_lots.id', 'stock_lots.quantity', 'stock_lots.unit_cost', 'reserved_quantity']);

        $physical = (float) $rows->sum(fn ($r) => (float) $r->quantity);
        $reserved = (float) $rows->sum(fn ($r) => (float) $r->reserved_quantity);
        $value = (float) $rows->sum(fn ($r) => (float) $r->quantity * (float) ($r->unit_cost ?? 0));

        return [
            'lots' => $rows->count(),
            'physical' => $physical,
            'reserved' => $reserved,
            'available' => $physical - $reserved,
            'value' => $value,
        ];
    }

    public function paginate(Builder $query, int $perPage = 25): LengthAwarePaginator
    {
        return $query
            ->orderBy('stock_lots.product_id')
            ->orderByRaw('CASE WHEN stock_lots.expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('stock_lots.expiry_date')
            ->orderBy('stock_lots.lot_number')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * [§12/13] Réservations GÉNÉRIQUES (product_id+warehouse_id, stock_lot_id
     * NULL, status=reserved) pour les produits lot-managed apparaissant dans
     * le résultat filtré — structurellement possibles via reserveForOrder()/
     * reserveStockForOrder() (produit fini vendu/livré, non touchées par
     * P1-D) : jamais réparties par lot, affichées seulement en avertissement
     * global pour ne pas présenter un « disponible » trompeur.
     *
     * @return Collection<int,array{product_id:int,warehouse_id:int,amount:float}>
     */
    public function genericReservationsFor(Collection $productWarehousePairs): Collection
    {
        if ($productWarehousePairs->isEmpty()) {
            return collect();
        }

        return StockReservation::query()
            ->whereNull('stock_lot_id')
            ->where('status', 'reserved')
            ->where(function ($q) use ($productWarehousePairs) {
                foreach ($productWarehousePairs as $pair) {
                    $q->orWhere(fn ($qq) => $qq->where('product_id', $pair['product_id'])->where('warehouse_id', $pair['warehouse_id']));
                }
            })
            ->selectRaw('product_id, warehouse_id, SUM(quantity - consumed_quantity) as amount')
            ->groupBy('product_id', 'warehouse_id')
            ->having('amount', '>', 0)
            ->get()
            ->map(fn ($r) => ['product_id' => (int) $r->product_id, 'warehouse_id' => (int) $r->warehouse_id, 'amount' => (float) $r->amount]);
    }
}
