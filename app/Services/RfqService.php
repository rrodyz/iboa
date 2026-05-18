<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\RfqQuote;
use App\Models\RfqQuoteItem;
use App\Models\RfqSupplier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [ACHATS-PRO-RFQ] Cycle de vie complet d'une demande de devis.
 *
 *   create()        : crée la RFQ en brouillon (items + fournisseurs consultés)
 *   markSent()      : passe en "envoyée" — fige les items, suppliers
 *   recordQuote()   : enregistre la cotation reçue d'un fournisseur
 *   compareQuotes() : agrège les cotations pour le tableau comparatif
 *   awardToQuote()  : désigne le gagnant + génère le PO en brouillon
 *   cancel()
 */
class RfqService
{
    public function create(array $data): Rfq
    {
        return DB::transaction(function () use ($data) {
            $company = Company::firstOrFail();

            $items     = $data['items']     ?? [];
            $suppliers = $data['supplier_ids'] ?? [];
            unset($data['items'], $data['supplier_ids']);

            if (count($items) === 0) {
                throw new \RuntimeException('Une RFQ doit comporter au moins une ligne.');
            }
            if (count($suppliers) === 0) {
                throw new \RuntimeException('Une RFQ doit cibler au moins un fournisseur.');
            }

            $rfq = Rfq::create([
                'company_id' => $company->id,
                'number'     => $this->nextNumber($company->id),
                'title'      => $data['title']    ?? 'Demande de devis',
                'status'     => 'brouillon',
                'deadline'   => $data['deadline'] ?? now()->addDays(7)->toDateString(),
                'notes'      => $data['notes']    ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($items as $i => $line) {
                $rfq->items()->create([
                    'product_id'  => $line['product_id'] ?? null,
                    'description' => $line['description'] ?? Product::find($line['product_id'] ?? 0)?->name ?? 'Article',
                    'quantity'    => (float) $line['quantity'],
                    'unit_id'     => $line['unit_id'] ?? null,
                    'sort_order'  => $i,
                ]);
            }

            foreach (array_unique($suppliers) as $supplierId) {
                $rfq->rfqSuppliers()->create([
                    'supplier_id' => (int) $supplierId,
                    'status'      => 'en_attente',
                ]);
            }

            return $rfq->fresh(['items', 'rfqSuppliers.supplier']);
        });
    }

    public function update(Rfq $rfq, array $data): Rfq
    {
        if (!$rfq->isEditable()) {
            throw new \RuntimeException("Cette RFQ est « {$rfq->statusLabel()} » — modification interdite.");
        }
        return DB::transaction(function () use ($rfq, $data) {
            $items     = $data['items']        ?? null;
            $suppliers = $data['supplier_ids'] ?? null;
            unset($data['items'], $data['supplier_ids']);

            $rfq->update(array_filter([
                'title'    => $data['title']    ?? null,
                'deadline' => $data['deadline'] ?? null,
                'notes'    => $data['notes']    ?? null,
            ], fn($v) => $v !== null));

            if ($items !== null) {
                $rfq->items()->delete();
                foreach ($items as $i => $line) {
                    $rfq->items()->create([
                        'product_id'  => $line['product_id'] ?? null,
                        'description' => $line['description'] ?? '',
                        'quantity'    => (float) $line['quantity'],
                        'unit_id'     => $line['unit_id'] ?? null,
                        'sort_order'  => $i,
                    ]);
                }
            }
            if ($suppliers !== null) {
                $rfq->rfqSuppliers()->delete();
                foreach (array_unique($suppliers) as $sid) {
                    $rfq->rfqSuppliers()->create(['supplier_id' => (int) $sid, 'status' => 'en_attente']);
                }
            }
            return $rfq->fresh(['items', 'rfqSuppliers.supplier']);
        });
    }

    /**
     * Marque la RFQ comme envoyée à tous les fournisseurs.
     */
    public function markSent(Rfq $rfq): Rfq
    {
        if (!$rfq->isDraft()) {
            throw new \RuntimeException("La RFQ doit être en brouillon pour être envoyée (actuelle : {$rfq->statusLabel()}).");
        }
        return DB::transaction(function () use ($rfq) {
            $rfq->update(['status' => 'envoyee']);
            $rfq->rfqSuppliers()->update([
                'status'  => 'envoyee',
                'sent_at' => now(),
            ]);
            return $rfq->fresh(['rfqSuppliers']);
        });
    }

    /**
     * Enregistre la cotation d'un fournisseur. Recalcule automatiquement les totaux.
     *
     * @param array{
     *     rfq_supplier_id:int,
     *     supplier_reference?:string|null,
     *     valid_until?:string|null,
     *     currency_code?:string,
     *     delivery_days?:int|null,
     *     notes?:string|null,
     *     items: array<int, array{rfq_item_id:int, unit_price:float, discount_percent?:float, tax_rate?:float, delivery_days?:int|null, notes?:string|null}>
     * } $data
     */
    public function recordQuote(Rfq $rfq, array $data): RfqQuote
    {
        return DB::transaction(function () use ($rfq, $data) {
            if ($rfq->isClosed() || $rfq->isCancelled()) {
                throw new \RuntimeException("Impossible d'enregistrer une cotation : RFQ {$rfq->statusLabel()}.");
            }

            $rfqSupplier = RfqSupplier::where('rfq_id', $rfq->id)
                ->where('id', $data['rfq_supplier_id'])
                ->firstOrFail();

            // Supprime une cotation antérieure (re-saisie)
            $rfqSupplier->quote?->delete();

            $quote = RfqQuote::create([
                'rfq_id'             => $rfq->id,
                'rfq_supplier_id'    => $rfqSupplier->id,
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'valid_until'        => $data['valid_until']        ?? null,
                'currency_code'      => $data['currency_code']      ?? 'XOF',
                'exchange_rate'      => 1,
                'subtotal_ht'        => 0,
                'total_tax'          => 0,
                'total_ttc'          => 0,
                'delivery_days'      => $data['delivery_days']      ?? null,
                'notes'              => $data['notes']              ?? null,
            ]);

            $subtotalHt = 0; $totalTax = 0;
            $rfqItems = $rfq->items->keyBy('id');

            foreach ($data['items'] ?? [] as $line) {
                $rfqItem = $rfqItems->get((int) $line['rfq_item_id']);
                if (!$rfqItem) continue;

                $qty      = (float) $rfqItem->quantity;
                $unit     = (float) $line['unit_price'];
                $disc     = (float) ($line['discount_percent'] ?? 0);
                $taxRate  = (float) ($line['tax_rate']         ?? 0);

                $gross    = $qty * $unit;
                $netHt    = $gross * (1 - $disc / 100);
                $tax      = $netHt * $taxRate / 100;
                $totalTtc = $netHt + $tax;

                $quote->items()->create([
                    'rfq_item_id'      => $rfqItem->id,
                    'unit_price'       => $unit,
                    'discount_percent' => $disc,
                    'tax_rate'         => $taxRate,
                    'line_total_ht'    => $netHt,
                    'line_total_ttc'   => $totalTtc,
                    'delivery_days'    => $line['delivery_days'] ?? null,
                    'notes'            => $line['notes']         ?? null,
                ]);

                $subtotalHt += $netHt;
                $totalTax   += $tax;
            }

            $quote->update([
                'subtotal_ht' => $subtotalHt,
                'total_tax'   => $totalTax,
                'total_ttc'   => $subtotalHt + $totalTax,
            ]);

            $rfqSupplier->update([
                'status'               => 'recue',
                'response_received_at' => now(),
            ]);

            // Statut global : RFQ passe en "recue" dès la 1ère réponse
            if ($rfq->isSent()) {
                $rfq->update(['status' => 'recue']);
            }

            return $quote->fresh('items');
        });
    }

    /**
     * Construit une matrice comparative : par item × par cotation, avec unit_price,
     * ligne HT, et marqueur du moins-disant. Plus un récap par cotation (totaux + grade).
     */
    public function compareQuotes(Rfq $rfq): array
    {
        $rfq->load(['items', 'quotes.items', 'quotes.rfqSupplier.supplier']);

        $quotes = $rfq->quotes;
        $items  = $rfq->items;

        // Index : [rfq_item_id][quote_id] => RfqQuoteItem
        $matrix = [];
        foreach ($quotes as $quote) {
            foreach ($quote->items as $qi) {
                $matrix[$qi->rfq_item_id][$quote->id] = $qi;
            }
        }

        // Pour chaque ligne RFQ, trouve le min unit_price
        $minByItem = [];
        foreach ($items as $item) {
            $vals = collect($matrix[$item->id] ?? [])
                ->filter(fn($qi) => $qi !== null)
                ->pluck('unit_price')
                ->map(fn($v) => (float) $v);
            $minByItem[$item->id] = $vals->isNotEmpty() ? $vals->min() : null;
        }

        // Synthèse par cotation
        $summary = $quotes->map(function ($q) {
            return [
                'quote'         => $q,
                'supplier_name' => $q->rfqSupplier?->supplier?->name ?? '—',
                'subtotal_ht'   => (float) $q->subtotal_ht,
                'total_ttc'     => (float) $q->total_ttc,
                'delivery_days' => $q->delivery_days,
                'valid_until'   => $q->valid_until,
                'is_winner'     => $q->is_winner,
            ];
        })->sortBy('total_ttc')->values()->all();

        return [
            'items'      => $items,
            'quotes'     => $quotes,
            'matrix'     => $matrix,
            'min_by_item'=> $minByItem,
            'summary'    => $summary,
            'best_total' => $summary[0] ?? null,
        ];
    }

    /**
     * Attribue le marché à une cotation et génère un PO en brouillon.
     */
    public function awardToQuote(Rfq $rfq, RfqQuote $quote): PurchaseOrder
    {
        if ($rfq->id !== $quote->rfq_id) {
            throw new \RuntimeException('Cotation incompatible avec cette RFQ.');
        }
        if ($rfq->isClosed() || $rfq->isCancelled()) {
            throw new \RuntimeException("RFQ déjà {$rfq->statusLabel()}, impossible d'attribuer à nouveau.");
        }

        return DB::transaction(function () use ($rfq, $quote) {
            $company   = Company::firstOrFail();
            $supplier  = $quote->rfqSupplier->supplier;
            $rfq->load('items');
            $quote->load('items.item');

            $po = PurchaseOrder::create([
                'company_id'     => $company->id,
                'supplier_id'    => $supplier->id,
                'number'         => $this->nextPoNumber($company->id),
                'status'         => 'brouillon',
                'ordered_at'     => now()->toDateString(),
                'expected_at'    => $quote->delivery_days
                    ? now()->addDays($quote->delivery_days)->toDateString()
                    : now()->addDays($supplier->avg_delivery_days ?: 7)->toDateString(),
                'currency_code'  => $quote->currency_code,
                'exchange_rate'  => $quote->exchange_rate,
                'subtotal_ht'    => $quote->subtotal_ht,
                'total_tax'      => $quote->total_tax,
                'total_ttc'      => $quote->total_ttc,
                'notes'          => "Issu de la RFQ {$rfq->number} (cotation {$quote->supplier_reference})",
                'created_by'     => Auth::id(),
            ]);

            foreach ($quote->items as $i => $qi) {
                $po->items()->create([
                    'product_id'        => $qi->item?->product_id,
                    'description'       => $qi->item?->description ?? '—',
                    'quantity'          => $qi->item?->quantity ?? 0,
                    'unit_price'        => $qi->unit_price,
                    'discount_percent'  => $qi->discount_percent,
                    'tax_rate_value'    => $qi->tax_rate,
                    'line_total_ht'     => $qi->line_total_ht,
                    'line_tax'          => max(0, $qi->line_total_ttc - $qi->line_total_ht),
                    'line_total_ttc'    => $qi->line_total_ttc,
                    'received_quantity' => 0,
                    'invoiced_quantity' => 0,
                    'sort_order'        => $i,
                ]);
            }

            // Marque la cotation gagnante + RFQ clôturée
            RfqQuote::where('rfq_id', $rfq->id)->update(['is_winner' => false]);
            $quote->update(['is_winner' => true]);
            $rfq->update([
                'status'            => 'cloturee',
                'awarded_quote_id'  => $quote->id,
                'purchase_order_id' => $po->id,
            ]);

            return $po;
        });
    }

    public function cancel(Rfq $rfq, string $reason): Rfq
    {
        if ($rfq->isClosed()) {
            throw new \RuntimeException("Impossible d'annuler une RFQ clôturée — annulez plutôt le PO généré.");
        }
        $rfq->update(['status' => 'annulee', 'notes' => ($rfq->notes ? $rfq->notes . "\n\n" : '') . 'Annulée : ' . $reason]);
        return $rfq;
    }

    private function nextNumber(int $companyId): string
    {
        $prefix = 'RFQ-' . now()->format('Y');
        $last = Rfq::where('company_id', $companyId)
            ->where('number', 'like', $prefix . '-%')
            ->orderByDesc('id')->value('number');
        $seq = $last ? (int) substr($last, strrpos($last, '-') + 1) + 1 : 1;
        return sprintf('%s-%04d', $prefix, $seq);
    }

    private function nextPoNumber(int $companyId): string
    {
        $prefix = 'BC-' . now()->format('Y');
        $last = PurchaseOrder::where('company_id', $companyId)
            ->where('number', 'like', $prefix . '-%')
            ->orderByDesc('id')->value('number');
        $seq = $last ? (int) substr($last, strrpos($last, '-') + 1) + 1 : 1;
        return sprintf('%s-%04d', $prefix, $seq);
    }
}
