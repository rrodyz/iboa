<?php

namespace App\Services;

use App\Models\Company;
use App\Models\SupplierReturn;
use App\Models\Warehouse;
use App\Repositories\SupplierReturnRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupplierReturnService
{
    public function __construct(
        public readonly SupplierReturnRepository $repository,
        private DocumentSequenceService $sequenceService,
        private StockService $stockService,
        private AccountingService $accountingService,
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->search($filters, $perPage);
    }

    public function create(array $data): SupplierReturn
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $company = currentCompany();

            $data['company_id']  = $company->id;
            $data['number']      = $this->sequenceService->nextNumber($company, 'retour_fournisseur');
            $data['created_by']  = Auth::id();
            $data['status']      = 'brouillon';

            [$subtotal, $taxTotal] = $this->calculateTotals($items);
            $data['subtotal_ht'] = $subtotal;
            $data['total_tax']   = $taxTotal;
            $data['total_ttc']   = $subtotal + $taxTotal;

            $return = SupplierReturn::create($data);
            $this->syncItems($return, $items);
            $this->recalculate($return);

            return $return->fresh();
        });
    }

    public function update(SupplierReturn $return, array $data): SupplierReturn
    {
        if (! $return->isEditable()) {
            throw new \RuntimeException('Ce retour ne peut plus être modifié.');
        }

        return DB::transaction(function () use ($return, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            $return->update($data);

            if ($items !== null) {
                $return->items()->delete();
                $this->syncItems($return, $items);
            }

            $this->recalculate($return);
            return $return->fresh();
        });
    }

    /**
     * Validate a return: change status to 'valide' and create outbound stock movements.
     */
    public function validate(SupplierReturn $return): SupplierReturn
    {
        if ($return->status !== 'brouillon') {
            throw new \RuntimeException('Seuls les retours en brouillon peuvent être validés.');
        }

        return DB::transaction(function () use ($return) {
            $return->load('items.product');

            $warehouseId = $return->warehouse_id
                ?? Warehouse::where('is_default', true)->value('id')
                ?? Warehouse::value('id');

            foreach ($return->items as $item) {
                if (!$item->product_id || $item->quantity <= 0 || !($item->product?->is_stockable ?? true)) {
                    continue;
                }

                // retour_fournisseur: outbound movement — stock leaves warehouse back to supplier
                $this->stockService->recordMovement([
                    'product_id'     => $item->product_id,
                    'warehouse_id'   => $warehouseId,
                    'type'           => 'retour_fournisseur',
                    'quantity'       => (float) $item->quantity,
                    'unit_cost'      => (float) $item->unit_price,
                    'occurred_at'    => $return->returned_at ?? now(),
                    'reference_type' => 'supplier_return',
                    'reference_id'   => $return->id,
                    'notes'          => 'Retour fournisseur ' . $return->number,
                ]);
            }

            $return->update([
                'status'       => 'valide',
                'validated_at' => now(),
                'validated_by' => Auth::id(),
            ]);

            $fresh = $return->fresh(['supplier', 'company']);

            // [FIX-ACHATS-04] Post GL entry and update supplier balance
            $this->accountingService->postSupplierReturn($fresh);
            // [COMPTA-STOCK] Sortie de stock automatique (retour fournisseur)
            $this->accountingService->postSupplierReturnStockMovement($fresh);
            $fresh->supplier?->recalculateBalance();

            return $fresh;
        });
    }

    public function delete(SupplierReturn $return): bool
    {
        if (! $return->isEditable()) {
            throw new \RuntimeException('Seuls les retours en brouillon peuvent être supprimés.');
        }
        return $return->delete();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function syncItems(SupplierReturn $return, array $items): void
    {
        foreach ($items as $i => $item) {
            if (empty($item['description']) && empty($item['product_id'])) {
                continue;
            }

            $qty      = (float) ($item['quantity'] ?? 1);
            $price    = (float) ($item['unit_price'] ?? 0);
            $discount = (float) ($item['discount_percent'] ?? 0);
            $tax      = (float) ($item['tax_rate_value'] ?? 0);
            $ht       = (int) round($qty * $price * (1 - $discount / 100));
            $lineTax  = (int) round($ht * ($tax / 100));
            $ttc      = $ht + $lineTax;

            $return->items()->create([
                'supplier_return_id' => $return->id,
                'product_id'         => $item['product_id'] ?? null,
                'description'        => $item['description'] ?? '',
                'unit_id'            => $item['unit_id'] ?? null,
                'quantity'           => $qty,
                'unit_price'         => (int) $price,
                'discount_percent'   => $discount,
                'tax_rate_id'        => $item['tax_rate_id'] ?? null,
                'tax_rate_value'     => $tax,
                'line_total_ht'      => $ht,
                'line_tax'           => $lineTax,
                'line_total_ttc'     => $ttc,
                'sort_order'         => $i,
            ]);
        }
    }

    private function calculateTotals(array $items): array
    {
        $subtotal = 0;
        $taxTotal = 0;

        foreach ($items as $item) {
            $qty      = (float) ($item['quantity'] ?? 1);
            $price    = (float) ($item['unit_price'] ?? 0);
            $discount = (float) ($item['discount_percent'] ?? 0);
            $tax      = (float) ($item['tax_rate_value'] ?? 0);
            $ht       = $qty * $price * (1 - $discount / 100);
            $subtotal += $ht;
            $taxTotal += $ht * ($tax / 100);
        }

        return [(int) round($subtotal), (int) round($taxTotal)];
    }

    private function recalculate(SupplierReturn $return): void
    {
        $return->load('items');

        $subtotal = (int) $return->items->sum('line_total_ht');
        $taxTotal = (int) $return->items->sum('line_tax');
        $total    = $subtotal + $taxTotal;

        $return->update([
            'subtotal_ht' => $subtotal,
            'total_tax'   => $taxTotal,
            'total_ttc'   => $total,
        ]);
    }
}
