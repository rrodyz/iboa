<?php

namespace App\Services;

use App\Events\OrderConfirmed;
use App\Models\Company;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\ProductStock;
use App\Repositories\OrderRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        public readonly OrderRepository $repository,
        private DocumentSequenceService $sequenceService,
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->search($filters, $perPage);
    }

    public function generateNumber(Company $company): string
    {
        return $this->sequenceService->nextNumber($company, 'commande');
    }

    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $company = currentCompany();

            $data['company_id']    = $company->id;
            $data['fiscal_year_id'] = $company->current_fiscal_year_id;
            $data['number']        = $this->sequenceService->nextNumber($company, 'commande');
            $data['created_by']    = Auth::id();
            $data['status']        = $data['status'] ?? 'brouillon';

            // [TVA-EXEMPT] Défense serveur : forcer TVA=0 si client exonéré
            $client = isset($data['client_id'])
                ? \App\Models\Client::find($data['client_id'])
                : null;
            if ($client?->isTaxExempt()) {
                $items = $this->zeroOutTax($items);
            }

            [$subtotal, $taxTotal] = $this->calculateTotals($items);
            $discount = (int) ($data['global_discount_amount'] ?? 0);

            $data['subtotal_ht']            = $subtotal;
            $data['total_discount']         = $discount;
            $data['total_tax']              = $taxTotal;
            $data['total_ttc']              = $subtotal + $taxTotal - $discount;
            $data['global_discount_amount'] = $discount;

            $order = Order::create($data);
            $this->syncItems($order, $items);
            $this->recalculate($order);

            return $order->fresh();
        });
    }

    public function update(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            $order->update($data);

            if ($items !== null) {
                // [FIX-QTE-01] When items are replaced on a confirmed/in-progress order,
                // release the reservations placed for the old quantities before deleting
                // the rows, then re-establish reservations for the new quantities.
                $reservingStatuses = ['confirme', 'en_preparation', 'partiellement_livre'];
                $needsResync = in_array($order->status, $reservingStatuses);

                if ($needsResync) {
                    $this->releaseStockReservations($order);
                }

                $order->items()->delete();
                $this->syncItems($order, $items);

                if ($needsResync) {
                    $this->reserveStock($order->fresh());
                }
            }

            $this->recalculate($order);
            return $order->fresh();
        });
    }

    public function delete(Order $order): bool
    {
        if (!in_array($order->status, ['brouillon', 'annule'])) {
            throw new \RuntimeException('Seules les commandes en brouillon ou annulées peuvent être supprimées.');
        }
        return $order->delete();
    }

    // ── Workflow transitions ──────────────────────────────────────────────────

    /** brouillon → confirme */
    public function confirm(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            // [ARCH-S2-01] Lock the order row inside the transaction to prevent
            // two simultaneous requests from double-confirming the same order.
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if ($order->status !== 'brouillon') {
                throw new \RuntimeException('Seule une commande en brouillon peut être confirmée.');
            }

            $order->update([
                'status'       => 'confirme',
                'validated_by' => Auth::id(),
                'validated_at' => now(),
            ]);

            $fresh = $order->fresh();

            // Fire event — ReserveStockOnOrderConfirmed listener handles stock
            // reservation; other listeners can handle notifications, CRM updates, etc.
            // Running synchronously inside this transaction guarantees atomicity.
            event(new OrderConfirmed($fresh));

            return $fresh;
        });
    }

    /** any → annule */
    public function cancel(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            // [ARCH-S2-02] Lock the order row to prevent concurrent cancel+confirm
            // or double-cancel races.
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if (in_array($order->status, ['annule', 'facture', 'livre'])) {
                throw new \RuntimeException('Cette commande ne peut pas être annulée (statut : ' . $order->status . ').');
            }

            // Release reservations that were placed when the order was confirmed.
            if (in_array($order->status, ['confirme', 'en_preparation', 'partiellement_livre'])) {
                $this->releaseStockReservations($order);
            }
            $order->update(['status' => 'annule']);
            return $order->fresh();
        });
    }

    /**
     * Check stock availability for all stockable items on this order.
     * Returns array: ['ok' => bool, 'lines' => [['description', 'required', 'available', 'ok']]]
     */
    public function checkStock(Order $order): array
    {
        $order->load('items.product');
        $lines = [];
        $allOk = true;

        foreach ($order->items as $item) {
            if (!$item->product_id || !($item->product?->is_stockable ?? false)) {
                continue;
            }

            $warehouseId = $order->delivery_warehouse_id;
            $query = ProductStock::where('product_id', $item->product_id);
            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }
            $stocks    = $query->get(['quantity', 'reserved_quantity']);
            $available = (float) $stocks->sum('quantity') - (float) $stocks->sum('reserved_quantity');

            $required  = (float) $item->quantity - (float) $item->delivered_quantity;
            $lineOk    = $available >= $required;

            if (!$lineOk) {
                $allOk = false;
            }

            $lines[] = [
                'description' => $item->description,
                'required'    => $required,
                'available'   => $available,
                'ok'          => $lineOk,
            ];
        }

        return ['ok' => $allOk, 'lines' => $lines];
    }

    /**
     * Convert the order to an invoice.
     */
    public function createInvoice(Order $order): Invoice
    {
        if (!in_array($order->status, ['confirme', 'en_preparation', 'partiellement_livre', 'livre'])) {
            throw new \RuntimeException('La commande doit être confirmée avant de générer une facture.');
        }
        return app(InvoiceService::class)->createFromOrder($order);
    }

    /**
     * Convert the order to a delivery note.
     */
    public function createDeliveryNote(Order $order): DeliveryNote
    {
        if (!in_array($order->status, ['confirme', 'en_preparation', 'partiellement_livre'])) {
            throw new \RuntimeException('La commande doit être confirmée avant de créer un bon de livraison.');
        }
        return app(DeliveryNoteService::class)->createFromOrder($order);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function syncItems(Order $order, array $items): void
    {
        foreach ($items as $i => $item) {
            if (empty($item['description']) && empty($item['product_id'])) {
                continue;
            }

            $qty   = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $disc  = (float) ($item['discount_percent'] ?? 0);
            $tax   = (float) ($item['tax_rate_value'] ?? 0);
            $ht    = (int) round($qty * $price * (1 - $disc / 100));
            $lineTax = (int) round($ht * ($tax / 100));
            $ttc   = $ht + $lineTax;

            $order->items()->create([
                'product_id'       => $item['product_id'] ?? null,
                'description'      => $item['description'] ?? '',
                'unit_id'          => $item['unit_id'] ?? null,
                'quantity'         => $qty,
                'unit_price'       => (int) $price,
                'discount_percent' => $disc,
                'tax_rate_id'      => $item['tax_rate_id'] ?? null,
                'tax_rate_value'   => $tax,
                'line_total_ht'    => $ht,
                'line_tax'         => $lineTax,
                'line_total_ttc'   => $ttc,
                'sort_order'       => $i,
            ]);
        }
    }

    /** [TVA-EXEMPT] Met tous les taux TVA à 0 sur un tableau d'items. */
    private function zeroOutTax(array $items): array
    {
        return array_map(function (array $item) {
            $item['tax_rate_value'] = 0;
            $item['tax_rate_id']    = null;
            $item['line_tax']       = 0;
            return $item;
        }, $items);
    }

    private function calculateTotals(array $items): array
    {
        $subtotal = 0;
        $taxTotal = 0;

        foreach ($items as $item) {
            $qty   = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $disc  = (float) ($item['discount_percent'] ?? 0);
            $tax   = (float) ($item['tax_rate_value'] ?? 0);
            $ht    = $qty * $price * (1 - $disc / 100);
            $subtotal += $ht;
            $taxTotal += $ht * ($tax / 100);
        }

        return [(int) round($subtotal), (int) round($taxTotal)];
    }

    /**
     * Increment reserved_quantity on ProductStock for every stockable item
     * that has not yet been fully delivered. Called when an order is confirmed.
     * Public so that QuoteService can call it when creating a confirmed order
     * directly from a quote (bypassing the brouillon → confirme transition).
     */
    public function reserveStock(Order $order): void
    {
        $order->load('items.product');
        $warehouseId = $order->delivery_warehouse_id;

        foreach ($order->items as $item) {
            if (!$item->product_id || !($item->product?->is_stockable ?? false)) {
                continue;
            }

            // Only reserve what still needs to be delivered
            $remaining = (float) $item->quantity - (float) $item->delivered_quantity;
            if ($remaining <= 0) {
                continue;
            }

            // [FIX-BUG-03] When delivery_warehouse_id is null, reserving via a
            // bare where('product_id', ...) would increment reserved_quantity on
            // EVERY warehouse row for the product (multiplying the reservation by
            // the number of warehouses). Pick a single ProductStock row instead:
            // prefer the warehouse with the highest available stock.
            if ($warehouseId) {
                // [ARCH-S2-03] Lock the specific row before incrementing to prevent
                // concurrent over-reservation on the same product/warehouse.
                $stockRow = ProductStock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $warehouseId)
                    ->lockForUpdate()
                    ->first();
                if ($stockRow) {
                    $stockRow->increment('reserved_quantity', $remaining);
                }
            } else {
                $candidate = ProductStock::where('product_id', $item->product_id)
                    ->orderByRaw('(quantity - reserved_quantity) DESC')
                    ->lockForUpdate()
                    ->first();
                if ($candidate) {
                    $candidate->increment('reserved_quantity', $remaining);
                }
            }
        }
    }

    /**
     * Decrement reserved_quantity for the undelivered portion of each stockable
     * item. Called when a confirmed/in-preparation/partially-delivered order is
     * cancelled so that the reserved stock becomes available again.
     */
    private function releaseStockReservations(Order $order): void
    {
        $order->load('items.product');
        $warehouseId = $order->delivery_warehouse_id;

        foreach ($order->items as $item) {
            if (!$item->product_id || !($item->product?->is_stockable ?? false)) {
                continue;
            }

            // Only release the still-undelivered portion; delivered qty has already
            // been consumed by physical stock-out movements.
            $undelivered = (float) $item->quantity - (float) $item->delivered_quantity;
            if ($undelivered <= 0) {
                continue;
            }

            $query = ProductStock::where('product_id', $item->product_id);
            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }

            // Never go below 0 — release only what is actually reserved.
            foreach ($query->get() as $stock) {
                $release = min($undelivered, (float) $stock->reserved_quantity);
                if ($release > 0) {
                    $stock->decrement('reserved_quantity', $release);
                }
            }
        }
    }

    private function recalculate(Order $order): void
    {
        $order->load('items');

        $subtotal = (int) $order->items->sum('line_total_ht');
        $taxTotal = (int) $order->items->sum('line_tax');
        $discount = (int) ($order->global_discount_amount ?? 0);
        $total    = $subtotal + $taxTotal - $discount;

        // [FIX-MAJEUR] Include total_discount in recalculate update
        $order->update([
            'subtotal_ht'    => $subtotal,
            'total_tax'      => $taxTotal,
            'total_discount' => $discount,
            'total_ttc'      => $total,
        ]);
    }
}
