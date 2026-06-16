<?php

namespace App\Services;

use App\Models\Company;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductStock;
use App\Models\Warehouse;
use App\Repositories\DeliveryNoteRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeliveryNoteService
{
    public function __construct(
        public readonly DeliveryNoteRepository $repository,
        private DocumentSequenceService $sequenceService,
        private StockService $stockService,
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->search($filters, $perPage);
    }

    /**
     * Create a delivery note from all items of an order.
     */
    public function createFromOrder(Order $order): DeliveryNote
    {
        return DB::transaction(function () use ($order) {
            $company = currentCompany();

            $dn = DeliveryNote::create([
                'company_id'       => $company->id,
                'client_id'        => $order->client_id,
                'order_id'         => $order->id,
                'number'           => $this->sequenceService->nextNumber($company, 'bon_livraison'),
                'issued_at'        => now()->toDateString(),
                'status'           => 'brouillon',
                'warehouse_id'     => $order->delivery_warehouse_id,
                'delivery_address' => $order->delivery_address,
                'currency_code'    => $order->currency_code,
                'created_by'       => Auth::id(),
            ]);

            $totalQty = 0;
            foreach ($order->items as $i => $item) {
                $dn->items()->create([
                    'order_item_id' => $item->id,
                    'product_id'    => $item->product_id,
                    'description'   => $item->description,
                    'unit_id'       => $item->unit_id,
                    'quantity'      => $item->quantity,
                    'unit_price'    => $item->unit_price,
                    'sort_order'    => $i,
                ]);
                $totalQty += (float) $item->quantity;
            }

            $dn->update(['total_quantity' => $totalQty]);

            return $dn;
        });
    }

    /**
     * Validate a delivery note: status -> valide, create stock-out movements,
     * update delivered_quantity on order items, and advance the order status.
     */
    public function validate(DeliveryNote $dn): DeliveryNote
    {
        if ($dn->status !== 'brouillon') {
            throw new \RuntimeException('Seuls les bons de livraison en brouillon peuvent être validés.');
        }

        // [VENTE↔PRODUCTION] Blocages livraison pour commandes fabriquées (QC + qté produite).
        app(\App\Modules\Production\Services\ProductionDeliveryGuard::class)->assertDeliverable($dn);

        return DB::transaction(function () use ($dn) {
            $dn->update([
                'status'       => 'valide',
                'validated_by' => Auth::id(),
                'validated_at' => now(),
            ]);

            $this->applyStockOut($dn);

            return $dn->fresh();
        });
    }

    /**
     * Applique les mouvements de sortie de stock pour un BL validé.
     * Méthode publique appelée à la fois par validate() et par CommercialWorkflowService::validateDeliveryNote()
     * pour le circuit interne (brouillon → en_attente_validation → valide).
     */
    public function applyStockOut(DeliveryNote $dn): void
    {
        $dn->load('items.product', 'order.items');

        $warehouseId = $dn->warehouse_id
            ?? Warehouse::where('is_default', true)->value('id')
            ?? Warehouse::value('id');

        foreach ($dn->items as $item) {
            if (!$item->product_id || !($item->product?->is_stockable ?? true)) {
                continue;
            }

            // Use the current average cost for valuation, not the sale price
            $avgCost = ProductStock::where('product_id', $item->product_id)
                ->where('warehouse_id', $warehouseId)
                ->value('avg_cost') ?? 0;

            $deliveredQty = abs((float) $item->quantity);

            $this->stockService->recordMovement([
                'product_id'     => $item->product_id,
                'warehouse_id'   => $warehouseId,
                'type'           => 'sortie',
                'quantity'       => $deliveredQty,
                'unit_cost'      => (float) $avgCost,
                'occurred_at'    => now(),
                'reference_type' => 'delivery_note',
                'reference_id'   => $dn->id,
                'notes'          => 'BL ' . $dn->number,
            ]);

            // [FIX-VENTES-06] The reservation placed at order confirmation is now consumed.
            // Iterate all matching rows (there may be multiple when the original reservation
            // was placed without a warehouse filter) so no phantom reserved_quantity remains.
            $remaining = $deliveredQty;
            ProductStock::where('product_id', $item->product_id)
                ->where('warehouse_id', $warehouseId)
                ->where('reserved_quantity', '>', 0)
                ->orderBy('id')
                ->each(function ($stockRow) use (&$remaining) {
                    if ($remaining <= 0) return false; // stop iteration
                    $release = min($remaining, (float) $stockRow->reserved_quantity);
                    $stockRow->decrement('reserved_quantity', $release);
                    $remaining -= $release;
                });

            // Update delivered_quantity on the linked order item
            if ($item->order_item_id) {
                $orderItem = OrderItem::find($item->order_item_id);
                if ($orderItem) {
                    $orderItem->increment('delivered_quantity', (float) $item->quantity);
                }
            }
        }

        // Advance order status based on delivery progress
        $this->syncOrderDeliveryStatus($dn->order);
    }

    /**
     * Create an invoice directly from a validated delivery note.
     * [FIX-CRITIQUE] Guard against double-invoicing the same BL.
     */
    public function createInvoice(DeliveryNote $dn): Invoice
    {
        if ($dn->status !== 'valide') {
            throw new \RuntimeException('Le bon de livraison doit être validé avant de générer une facture.');
        }

        // Prevent double-invoicing
        if (Invoice::where('delivery_note_id', $dn->id)->exists()) {
            throw new \RuntimeException('Une facture a déjà été générée pour ce bon de livraison (' . $dn->number . ').');
        }

        return app(InvoiceService::class)->createFromDeliveryNote($dn);
    }

    /** brouillon → annule */
    public function cancel(DeliveryNote $dn): DeliveryNote
    {
        if ($dn->status !== 'brouillon') {
            throw new \RuntimeException('Seuls les bons de livraison en brouillon peuvent être annulés.');
        }
        $dn->update(['status' => 'annule']);
        return $dn->fresh();
    }

    /**
     * [FIX-CRITIQUE] Annuler un BL déjà validé : reverse stock movements,
     * decrement delivered_quantity on order items, and re-sync order status.
     */
    public function cancelValidated(DeliveryNote $dn): DeliveryNote
    {
        if ($dn->status !== 'valide') {
            throw new \RuntimeException('Seuls les bons de livraison validés peuvent être annulés via cette procédure.');
        }

        // Cannot cancel if an invoice already exists
        if (Invoice::where('delivery_note_id', $dn->id)->whereNotIn('status', ['annulee'])->exists()) {
            throw new \RuntimeException('Impossible d\'annuler : une facture non-annulée est liée à ce bon de livraison.');
        }

        return DB::transaction(function () use ($dn) {
            $dn->load('items.product', 'order');

            $warehouseId = $dn->warehouse_id
                ?? Warehouse::where('is_default', true)->value('id')
                ?? Warehouse::value('id');

            foreach ($dn->items as $item) {
                if (!$item->product_id || !($item->product?->is_stockable ?? true)) {
                    continue;
                }

                // Reverse: create a stock-in movement to restore quantity
                $avgCost = \App\Models\ProductStock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $warehouseId)
                    ->value('avg_cost') ?? 0;

                $reversedQty = abs((float) $item->quantity);

                $this->stockService->recordMovement([
                    'product_id'     => $item->product_id,
                    'warehouse_id'   => $warehouseId,
                    'type'           => 'entree',
                    'quantity'       => $reversedQty,
                    'unit_cost'      => (float) $avgCost,
                    'occurred_at'    => now(),
                    'reference_type' => 'delivery_note',
                    'reference_id'   => $dn->id,
                    'notes'          => 'Annulation BL ' . $dn->number,
                ]);

                // [FIX-VENTES-07] Re-establish the reservation only when the parent order
                // is still active (confirmed / in-preparation / partially delivered).
                // If the order is cancelled or already fully invoiced there is no one
                // waiting for the stock, so adding a phantom reservation would block sales.
                $orderStatus = $dn->order?->status;
                if ($orderStatus && in_array($orderStatus, ['confirme', 'en_preparation', 'partiellement_livre'])) {
                    ProductStock::where('product_id', $item->product_id)
                        ->where('warehouse_id', $warehouseId)
                        ->increment('reserved_quantity', $reversedQty);
                }

                // Decrement delivered_quantity (and invoiced_quantity if applicable) on the linked order item
                if ($item->order_item_id) {
                    $orderItem = \App\Models\OrderItem::find($item->order_item_id);
                    if ($orderItem) {
                        $orderItem->decrement('delivered_quantity', (float) $item->quantity);

                        // [FIX-QTE-02] Also decrement invoiced_quantity if items had been invoiced
                        if ((float) $orderItem->invoiced_quantity > 0) {
                            $decrement = min((float) $item->quantity, (float) $orderItem->invoiced_quantity);
                            $orderItem->decrement('invoiced_quantity', $decrement);
                        }
                    }
                }
            }

            $dn->update(['status' => 'annule']);

            // Re-sync order delivery status
            $this->syncOrderDeliveryStatus($dn->order);

            return $dn->fresh();
        });
    }

    /**
     * Sync the parent order status based on all its delivery notes' progress.
     */
    private function syncOrderDeliveryStatus(?Order $order): void
    {
        if (!$order) return;

        $order->load('items', 'deliveryNotes');

        // Count validated delivery notes
        $validatedBls = $order->deliveryNotes->where('status', 'valide')->count();
        if ($validatedBls === 0) return;

        // Check if all order items are fully delivered
        $allDelivered = $order->items->every(function ($item) {
            return (float) $item->delivered_quantity >= (float) $item->quantity;
        });

        $newStatus = $allDelivered ? 'livre' : 'partiellement_livre';

        if (!in_array($order->status, ['facture', 'annule'])) {
            $order->update(['status' => $newStatus]);
        }
    }

    /**
     * Generate PDF view path for the delivery note.
     */
    public function generatePdfPath(DeliveryNote $dn): string
    {
        return 'ventes.pdf.delivery-note';
    }

    public function delete(DeliveryNote $dn): bool
    {
        if ($dn->status !== 'brouillon') {
            throw new \RuntimeException('Seuls les bons de livraison en brouillon peuvent être supprimés.');
        }

        return $dn->delete();
    }
}
