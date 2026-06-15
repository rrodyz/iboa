<?php

namespace App\Modules\Production\Services;
use App\Services\StockService;

use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use App\Modules\Production\Models\ProductionWaste;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * [PRODUCTION] Sorties de production (produits finis) et pertes/chutes.
 *
 * Les produits finis entrent dans le stock existant (product_stocks) via
 * StockService — réutilisation du module stock, pas de table parallèle.
 * Les chutes/rebuts sont tracés dans production_wastes (valorisation indicative).
 */
class ProductionStockService
{
    public function __construct(private StockService $stock) {}

    /** Enregistre une sortie de produit fini + entrée en stock. */
    public function recordOutput(ProductionOrder $order, array $data): ProductionOutput
    {
        if (! $order->isInProgress()) {
            throw ValidationException::withMessages(['status' => 'La production n\'est possible que sur un OF « en cours ».']);
        }

        $length   = (float) ($data['length'] ?? 0);
        $quantity = (float) ($data['quantity'] ?? 0);
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La quantité produite doit être positive.']);
        }

        return DB::transaction(function () use ($order, $data, $length, $quantity) {
            $totalMeters = round($length * $quantity, 2);
            $productId   = $data['product_id'] ?? $order->product_id;
            $warehouseId = $data['warehouse_id'] ?? $this->defaultWarehouseId($order);
            $unitCost    = (float) ($data['unit_cost'] ?? 0);

            $movementId = null;

            // Entrée en stock du produit fini (si produit + entrepôt connus)
            if ($productId && $warehouseId) {
                $movement = $this->stock->recordMovement([
                    'product_id'     => $productId,
                    'warehouse_id'   => $warehouseId,
                    'type'           => 'entree',
                    'quantity'       => $quantity,
                    'unit_cost'      => $unitCost,
                    'reference_type' => ProductionOrder::class,
                    'reference_id'   => $order->id,
                    'notes'          => 'Production OF ' . $order->number,
                ]);
                $movementId = $movement->id;
            }

            $output = $order->outputs()->create([
                'company_id'        => $order->company_id,
                'product_id'        => $productId,
                'length'            => $length,
                'color'             => $data['color'] ?? $order->color,
                'thickness'         => $data['thickness'] ?? $order->thickness,
                'quantity'          => $quantity,
                'total_meters'      => $totalMeters,
                'unit_id'           => $data['unit_id'] ?? null,
                'warehouse_id'      => $warehouseId,
                'stock_movement_id' => $movementId,
                'produced_at'       => $data['produced_at'] ?? now(),
                'created_by'        => Auth::id(),
            ]);

            $order->increment('quantity_produced', $quantity);

            return $output;
        });
    }

    /** Annule une sortie : retire le produit fini du stock. */
    public function reverseOutput(ProductionOutput $output): void
    {
        $order = $output->productionOrder;
        if ($order && ! $order->isInProgress()) {
            throw ValidationException::withMessages(['status' => 'Annulation impossible : l\'OF n\'est plus « en cours ».']);
        }

        DB::transaction(function () use ($output) {
            $order = $output->productionOrder;

            if ($output->stock_movement_id && $output->product_id && $output->warehouse_id) {
                $this->stock->recordMovement([
                    'product_id'     => $output->product_id,
                    'warehouse_id'   => $output->warehouse_id,
                    'type'           => 'sortie',
                    'quantity'       => $output->quantity,
                    'reference_type' => ProductionOrder::class,
                    'reference_id'   => $order?->id,
                    'notes'          => 'Annulation sortie production',
                ]);
            }

            if ($order) {
                $order->decrement('quantity_produced', $output->quantity);
            }
            $output->delete();
        });
    }

    /** Enregistre une perte / chute. */
    public function recordWaste(ProductionOrder $order, array $data): ProductionWaste
    {
        $weight   = (float) ($data['weight'] ?? 0);
        $quantity = (float) ($data['quantity'] ?? 0);
        if ($weight <= 0 && $quantity <= 0) {
            throw ValidationException::withMessages(['weight' => 'Renseignez un poids ou une quantité de chute.']);
        }

        $unitCost = (float) ($data['unit_cost'] ?? $this->averageConsumedCostPerKg($order));
        $value    = (int) round($weight * $unitCost);

        return $order->wastes()->create([
            'company_id'  => $order->company_id,
            'machine_id'  => $data['machine_id'] ?? null,
            'operator_id' => $data['operator_id'] ?? null,
            'type'        => $data['type'] ?? 'non_reutilisable',
            'quantity'    => $quantity,
            'weight'      => $weight,
            'value'       => $value,
            'reason'      => $data['reason'] ?? null,
            'created_by'  => Auth::id(),
        ]);
    }

    public function reverseWaste(ProductionWaste $waste): void
    {
        $order = $waste->productionOrder;
        if ($order && ! $order->isInProgress()) {
            throw ValidationException::withMessages(['status' => 'Annulation impossible : l\'OF n\'est plus « en cours ».']);
        }
        $waste->delete();
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function defaultWarehouseId(ProductionOrder $order): ?int
    {
        return Warehouse::where('company_id', $order->company_id)
            ->orderByDesc('is_default')->orderBy('id')->value('id');
    }

    /** Coût moyen au kg réellement consommé sur l'OF (sinon 0). */
    private function averageConsumedCostPerKg(ProductionOrder $order): float
    {
        $totalWeight = (float) $order->consumptions()->sum('weight_consumed');
        if ($totalWeight <= 0) {
            return 0.0;
        }

        return round((float) $order->consumptions()->sum('cost') / $totalWeight, 2);
    }
}
