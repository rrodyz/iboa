<?php

namespace App\Services;

use App\Models\Company;
use App\Models\InventorySession;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [STOCK-PRO] Cycle de vie des transferts inter-dépôts.
 *
 *   ┌───────────┐  ship    ┌───────────┐  receive  ┌──────┐
 *   │ brouillon │ ───────▶ │ en_transit│ ────────▶ │ recu │
 *   └─────┬─────┘          └─────┬─────┘           └──────┘
 *         │                      │
 *         └────── cancel ────────┴─────────────▶  annule
 *
 * - ship   : décrémente le stock source, marque en_transit
 * - receive: incrémente le stock destination, accepte des écarts (received_quantity)
 * - cancel : si en_transit → reverse l'expédition (réintègre stock source)
 *
 * Toutes les opérations sont transactionnelles avec lockForUpdate sur le transfert
 * pour éviter les doubles-validations concurrentes.
 */
class StockTransferService
{
    public function __construct(
        private DocumentSequenceService $sequenceService,
    ) {}

    /**
     * Crée un transfert en brouillon.
     */
    public function create(array $data): StockTransfer
    {
        return DB::transaction(function () use ($data) {
            $company = currentCompany();

            if (($data['from_warehouse_id'] ?? null) === ($data['to_warehouse_id'] ?? null)) {
                throw new \RuntimeException('Le dépôt source et le dépôt destination ne peuvent pas être identiques.');
            }

            $items = $data['items'] ?? [];
            unset($data['items']);

            // [Maquette X3] champs entête additionnels (transport, priorité, contrôles…)
            $extra = array_intersect_key($data, array_flip([
                'transfer_time', 'type', 'priority', 'currency_code', 'reference',
                'source_document_date', 'responsible_id', 'carrier', 'transport_mode',
                'vehicle', 'driver', 'planned_date', 'planned_time', 'transport_cost',
                'grouping', 'packages_count', 'total_weight', 'total_volume',
                'controlled_by', 'controlled_at', 'validation_status',
            ]));

            $transfer = StockTransfer::create($extra + [
                'company_id'        => $company->id,
                'number'            => $this->sequenceService->nextNumber($company, 'transfert_stock')
                                       ?? $this->fallbackNumber($company),
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id'   => $data['to_warehouse_id'],
                'transfer_date'     => $data['transfer_date'] ?? now()->toDateString(),
                'status'            => 'brouillon',
                'reason'            => $data['reason'] ?? null,
                'notes'             => $data['notes']  ?? null,
                'created_by'        => Auth::id(),
            ]);

            $this->syncItems($transfer, $items);

            return $transfer->fresh('items');
        });
    }

    /**
     * Met à jour un transfert en brouillon.
     */
    public function update(StockTransfer $transfer, array $data): StockTransfer
    {
        if (!$transfer->canEdit()) {
            throw new \RuntimeException("Ce transfert est « {$transfer->statusLabel()} » — modification interdite.");
        }

        return DB::transaction(function () use ($transfer, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            $transfer->update(array_filter([
                'from_warehouse_id' => $data['from_warehouse_id'] ?? null,
                'to_warehouse_id'   => $data['to_warehouse_id']   ?? null,
                'transfer_date'     => $data['transfer_date']     ?? null,
                'reason'            => $data['reason']            ?? null,
                'notes'             => $data['notes']             ?? null,
            ], fn($v) => $v !== null));

            if ($items !== null) {
                $transfer->items()->delete();
                $this->syncItems($transfer, $items);
            }

            return $transfer->fresh('items');
        });
    }

    /**
     * Étape 1 : Expédier — décrémente le stock source, statut en_transit.
     */
    public function ship(StockTransfer $transfer): StockTransfer
    {
        return DB::transaction(function () use ($transfer) {
            // Lock pour éviter double-expédition concurrente
            $transfer = StockTransfer::lockForUpdate()->findOrFail($transfer->id);
            $transfer->load('items');

            if (!$transfer->canShip()) {
                throw new \RuntimeException("Ce transfert ne peut pas être expédié (statut actuel : {$transfer->statusLabel()}).");
            }
            if ($transfer->items->isEmpty()) {
                throw new \RuntimeException('Impossible d\'expédier un transfert sans aucune ligne.');
            }

            // [CDC §8.5] Même garde-fou que StockService::recordMovement() : pas
            // d'expédition depuis un dépôt en cours d'inventaire.
            if (InventorySession::where('warehouse_id', $transfer->from_warehouse_id)->where('status', 'en_cours')->exists()) {
                throw new \RuntimeException('Expédition bloquée : un inventaire est en cours sur le dépôt source.');
            }

            // Vérifie stock dispo en amont — meilleur message d'erreur. [P1-F-C]
            // ProductStock source verrouillé ICI (lockForUpdate), AVANT le calcul
            // de disponibilité et AVANT toute écriture : sans ce verrou, deux
            // ship() concurrents sur le même produit+dépôt lisaient tous deux le
            // même solde non verrouillé, passaient tous deux la validation, puis
            // décrémentaient tous deux — stock pouvant devenir négatif (aucune
            // contrainte CHECK en base ne l'empêchait). [P1-F] Résout aussi le
            // StockLot source ici (avant toute écriture) : un lot_number inconnu,
            // appartenant à un autre article/dépôt, ou en quantité insuffisante
            // doit bloquer TOUT le transfert sans le moindre effet de bord —
            // ordre de verrouillage stable : ProductStock puis StockLot, dans ce
            // même ordre pour ship() et receive() (limite le risque de deadlock).
            $sourceStocks = [];
            $sourceLots = [];
            foreach ($transfer->items as $item) {
                $stock = ProductStock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $transfer->from_warehouse_id)
                    ->lockForUpdate()
                    ->first();
                $available = $stock ? (float) $stock->quantity - (float) ($stock->reserved_quantity ?? 0) : 0.0;
                if ($available < (float) $item->quantity) {
                    throw new \RuntimeException(sprintf(
                        'Stock insuffisant pour %s au dépôt source : disponible %s, demandé %s.',
                        $item->product?->name ?? '#'.$item->product_id,
                        number_format($available, 2, ',', ' '),
                        number_format($item->quantity, 2, ',', ' ')
                    ));
                }
                $sourceStocks[$item->id] = $stock;

                if (! empty($item->lot_number)) {
                    // La recherche par (product_id, warehouse_id, lot_number) rejette
                    // NATURELLEMENT un lot d'un autre article ou d'un autre dépôt —
                    // il ne sera simplement pas trouvé (mêmes garde-fous que #29/#30).
                    $lot = StockLot::where('product_id', $item->product_id)
                        ->where('warehouse_id', $transfer->from_warehouse_id)
                        ->where('lot_number', $item->lot_number)
                        ->lockForUpdate()
                        ->first();

                    if (! $lot) {
                        throw new \RuntimeException(sprintf(
                            'Lot « %s » introuvable pour %s au dépôt source — aucun autre lot n\'est substitué automatiquement.',
                            $item->lot_number,
                            $item->product?->name ?? '#'.$item->product_id
                        ));
                    }
                    if ((float) $lot->quantity < (float) $item->quantity) {
                        throw new \RuntimeException(sprintf(
                            'Lot « %s » : quantité disponible %s, quantité demandée %s.',
                            $item->lot_number,
                            number_format((float) $lot->quantity, 4, ',', ' '),
                            number_format((float) $item->quantity, 4, ',', ' ')
                        ));
                    }

                    $sourceLots[$item->id] = $lot;
                }
            }

            // Décrément source + mouvement type sortie
            foreach ($transfer->items as $item) {
                // [P1-F-C] Réutilise l'instance déjà verrouillée (lockForUpdate) dans
                // la boucle de pré-vérification ci-dessus — le verrou est tenu tout au
                // long de cette même transaction. Le fallback firstOrCreate() ne peut
                // être atteint que si la ligne a été créée après la boucle de
                // pré-vérification, ce qui n'arrive jamais ici (quantité toujours >0,
                // donc une ligne absente aurait déjà fait échouer la validation).
                $stock = $sourceStocks[$item->id] ?? ProductStock::firstOrCreate(
                    ['product_id' => $item->product_id, 'warehouse_id' => $transfer->from_warehouse_id],
                    ['quantity' => 0, 'reserved_quantity' => 0]
                );

                $sourceLot = $sourceLots[$item->id] ?? null;

                // [FIX valorisation] Si aucun coût n'a été saisi sur la ligne (cas normal :
                // l'UI ne demande pas de coût), reprendre le CMP du dépôt SOURCE et le
                // persister sur la ligne — la réception l'utilisera pour valoriser l'entrée.
                // Sans cela, sortie + entrée partaient à 0 F et le CMP destination était
                // écrasé à 0 (stock sous-évalué).
                // [P1-F] Pour un article loté, le coût du LOT lui-même (unit_cost déjà
                // figé à réception) est une source plus précise que le CMP produit
                // générique — préférée quand disponible, avant le fallback existant.
                if ((float) ($item->unit_cost ?? 0) <= 0) {
                    $sourceCost = $sourceLot ? (float) $sourceLot->unit_cost : 0.0;
                    if ($sourceCost <= 0) {
                        $sourceCost = (float) ($stock->avg_cost ?? 0);
                    }
                    if ($sourceCost <= 0) {
                        $sourceCost = (float) ($item->product?->weighted_avg_cost ?? 0);
                    }
                    if ($sourceCost > 0) {
                        $item->update(['unit_cost' => $sourceCost]);
                        $item->refresh();
                    }
                }

                $stock->decrement('quantity', (float) $item->quantity);
                $stock->update(['last_movement_at' => now()]);

                // [P1-F] Répercute le transfert sur le grand livre des lots — décrémente
                // la ligne SOURCE (jamais supprimée, même à 0 : cohérent avec la
                // convention StockLot existante, cf. §16 du rapport final).
                if ($sourceLot) {
                    $sourceLot->decrement('quantity', (float) $item->quantity);
                }

                StockMovement::create([
                    'product_id'        => $item->product_id,
                    'warehouse_id'      => $transfer->from_warehouse_id,
                    'type'              => 'sortie',
                    'reference_type'    => StockTransfer::class,
                    'reference_id'      => $transfer->id,
                    'quantity'          => $item->quantity,
                    'unit_cost'         => $item->unit_cost ?? 0,
                    'total_cost'        => ($item->unit_cost ?? 0) * (float) $item->quantity,
                    'from_warehouse_id' => $transfer->from_warehouse_id,
                    'to_warehouse_id'   => $transfer->to_warehouse_id,
                    'lot_number'        => $item->lot_number,
                    'stock_lot_id'      => $sourceLot?->id,
                    'serial_number'     => $item->serial_number,
                    'expiry_date'       => $item->expiry_date,
                    'notes'             => "Transfert {$transfer->number} — expédition",
                    'created_by'        => Auth::id(),
                    'occurred_at'       => now(),
                ]);
            }

            $transfer->update([
                'status'     => 'en_transit',
                'shipped_at' => now(),
                'shipped_by' => Auth::id(),
            ]);

            return $transfer->fresh('items');
        });
    }

    /**
     * Étape 2 : Recevoir — incrémente le stock destination avec qty effective.
     *
     * $receivedQuantities = [item_id => qty_effective]. Si non fourni pour une ligne,
     * on prend la quantité d'expédition par défaut (transfert sans écart).
     */
    public function receive(StockTransfer $transfer, array $receivedQuantities = []): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $receivedQuantities) {
            $transfer = StockTransfer::lockForUpdate()->findOrFail($transfer->id);
            $transfer->load('items');

            if (!$transfer->canReceive()) {
                throw new \RuntimeException("Ce transfert ne peut pas être réceptionné (statut : {$transfer->statusLabel()}).");
            }

            // [CDC §8.5] Même garde-fou : pas de réception sur un dépôt en cours d'inventaire.
            if (InventorySession::where('warehouse_id', $transfer->to_warehouse_id)->where('status', 'en_cours')->exists()) {
                throw new \RuntimeException('Réception bloquée : un inventaire est en cours sur le dépôt destination.');
            }

            foreach ($transfer->items as $item) {
                $received = isset($receivedQuantities[$item->id])
                    ? max(0, (float) $receivedQuantities[$item->id])
                    : (float) $item->quantity;

                if ($received > (float) $item->quantity) {
                    throw new \RuntimeException(sprintf(
                        'Ligne %s : quantité reçue (%s) supérieure à la quantité expédiée (%s).',
                        $item->product?->name ?? '#'.$item->product_id,
                        $received, $item->quantity
                    ));
                }

                $item->update(['received_quantity' => $received]);

                if ($received > 0) {
                    // [P1-F-C] Verrouillé : deux receive() concurrents sur le même
                    // produit+dépôt destination ne doivent pas lire/recalculer avg_cost
                    // à partir du même solde non verrouillé (lost update sur avg_cost),
                    // même si l'incrément brut de quantity reste atomique côté SQL.
                    $stock = $this->lockOrCreateProductStock($item->product_id, $transfer->to_warehouse_id);

                    // [FIX valorisation] Recalcul du CMP destination à la réception :
                    // nouveau CMP = (stock×CMP + reçu×coût transféré) / (stock + reçu).
                    // Sans cela, avg_cost destination restait à 0 pour un dépôt qui ne
                    // possédait pas l'article → stock sous-évalué en valorisation.
                    $unitCost = (float) ($item->unit_cost ?? 0);
                    if ($unitCost > 0) {
                        $oldQty  = (float) $stock->quantity;
                        $oldAvg  = (float) ($stock->avg_cost ?? 0);
                        $newAvg  = ($oldQty + $received) > 0
                            ? (($oldQty * $oldAvg) + ($received * $unitCost)) / ($oldQty + $received)
                            : $unitCost;
                        $stock->update(['avg_cost' => round($newAvg, 4)]);
                    }

                    $stock->increment('quantity', $received);
                    $stock->update(['last_movement_at' => now()]);

                    // [P1-F] Répercute la réception sur le grand livre des lots — même
                    // ordre de verrouillage que ship() : StockLot avant ProductStock
                    // (ProductStock ci-dessus n'est pas verrouillé explicitement, comme
                    // avant ce fix — non modifié, hors périmètre P1-F).
                    $destLot = null;
                    if (! empty($item->lot_number)) {
                        $destLot = StockLot::where('product_id', $item->product_id)
                            ->where('warehouse_id', $transfer->to_warehouse_id)
                            ->where('lot_number', $item->lot_number)
                            ->lockForUpdate()
                            ->first();

                        if ($destLot) {
                            // Lot déjà présent au dépôt destination (§17) : cumule, ne
                            // duplique jamais (contrainte unique product+warehouse+lot).
                            $destLot->increment('quantity', $received);
                            $destLot->increment('initial_quantity', $received);
                        } else {
                            // Lot absent au dépôt destination (§18) : création contrôlée.
                            // Métadonnées héritées du lot SOURCE quand il existe encore
                            // (jamais supprimé par ship(), seulement décrémenté) — évite
                            // de relancer un cycle qualité/valorisation déjà tranché sur
                            // le même lot physique. Ce qui N'EST PAS copié : reserved_quantity
                            // (lié au dépôt, colonne d'ailleurs inutilisée en base ce jour),
                            // status/received_at (recalculés pour l'arrivée destination),
                            // qty_released/qty_quarantine/qty_rejected (ventilation d'une
                            // décision qualité ponctuelle, non proratisable sans règle
                            // métier dédiée — hors périmètre P1-F).
                            $sourceLotForMeta = StockLot::where('product_id', $item->product_id)
                                ->where('warehouse_id', $transfer->from_warehouse_id)
                                ->where('lot_number', $item->lot_number)
                                ->first();

                            $destLot = StockLot::create([
                                'product_id'          => $item->product_id,
                                'warehouse_id'        => $transfer->to_warehouse_id,
                                'lot_number'          => $item->lot_number,
                                'supplier_lot_number' => $sourceLotForMeta?->supplier_lot_number,
                                'serial_number'       => $item->serial_number ?? $sourceLotForMeta?->serial_number,
                                'expiry_date'         => $item->expiry_date ?? $sourceLotForMeta?->expiry_date,
                                'quantity'            => $received,
                                'initial_quantity'    => $received,
                                'reserved_quantity'   => 0,
                                'stock_uom'           => $sourceLotForMeta?->stock_uom,
                                'kg_per_linear_meter' => $sourceLotForMeta?->kg_per_linear_meter,
                                'unit_cost'           => $item->unit_cost ?? ($sourceLotForMeta->unit_cost ?? 0),
                                'received_at'         => now()->toDateString(),
                                'status'              => 'disponible',
                                'quality_status'      => $sourceLotForMeta?->quality_status,
                                'valuation_status'     => $sourceLotForMeta?->valuation_status ?? 'valorisation_definitive',
                                'valuation_reason'     => $sourceLotForMeta?->valuation_reason,
                                'source_type'          => StockTransfer::class,
                                'source_id'            => $transfer->id,
                                'created_by'           => Auth::id(),
                            ]);
                        }
                    }

                    StockMovement::create([
                        'product_id'        => $item->product_id,
                        'warehouse_id'      => $transfer->to_warehouse_id,
                        'type'              => 'entree',
                        'reference_type'    => StockTransfer::class,
                        'reference_id'      => $transfer->id,
                        'quantity'          => $received,
                        'unit_cost'         => $item->unit_cost ?? 0,
                        'total_cost'        => ($item->unit_cost ?? 0) * $received,
                        'from_warehouse_id' => $transfer->from_warehouse_id,
                        'to_warehouse_id'   => $transfer->to_warehouse_id,
                        'lot_number'        => $item->lot_number,
                        'stock_lot_id'      => $destLot?->id,
                        'serial_number'     => $item->serial_number,
                        'expiry_date'       => $item->expiry_date,
                        'notes'             => "Transfert {$transfer->number} — réception"
                                            . ($received < (float) $item->quantity ? " (écart -" . ($item->quantity - $received) . ")" : ''),
                        'created_by'        => Auth::id(),
                        'occurred_at'       => now(),
                    ]);
                }
            }

            // [DÉCISION 23/07 — écart de transfert] Les unités expédiées et non
            // reçues sont une PERTE EN TRANSIT : le stock physique la reflète déjà
            // (sortie source sans entrée destination) ; la valorisation comptable
            // doit suivre (D 6097 Pertes / C 311x Stocks), sinon la perte est
            // invisible en balance.
            $lossTotal = 0;
            $lossDetails = [];
            foreach ($transfer->items as $item) {
                $ecart = (float) $item->quantity - (float) ($item->received_quantity ?? $item->quantity);
                if ($ecart > 0.0001) {
                    $val = (int) round($ecart * (float) ($item->unit_cost ?? 0));
                    $lossTotal += $val;
                    $lossDetails[] = ($item->product?->name ?? '#' . $item->product_id) . " : -{$ecart} (" . number_format($val, 0, ',', ' ') . ' FCFA)';
                }
            }
            if ($lossTotal > 0) {
                app(AccountingService::class)->postTransferLoss($transfer, $lossTotal);
                app(AuditService::class)->log('transfert.perte_transit', $transfer, [], [
                    'montant' => $lossTotal, 'detail' => implode(' ; ', $lossDetails),
                ]);
            }

            $transfer->update([
                'status'      => 'recu',
                'received_at' => now(),
                'received_by' => Auth::id(),
            ]);

            return $transfer->fresh('items');
        });
    }

    /**
     * Annule un transfert. Si en_transit, ré-incrémente le stock source.
     */
    public function cancel(StockTransfer $transfer, string $reason): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $reason) {
            $transfer = StockTransfer::lockForUpdate()->findOrFail($transfer->id);
            $transfer->load('items');

            if (!$transfer->canCancel()) {
                throw new \RuntimeException("Ce transfert ne peut pas être annulé (statut : {$transfer->statusLabel()}).");
            }

            // Si on annule un transfert déjà expédié, on réintègre le stock source.
            // [P1-F-C] ProductStock source verrouillé : un cancel() concurrent d'un
            // autre transfert sur le même produit+dépôt (ex. un nouveau ship())
            // ne doit pas se croiser avec cette réintégration.
            if ($transfer->isInTransit()) {
                foreach ($transfer->items as $item) {
                    $stock = $this->lockOrCreateProductStock($item->product_id, $transfer->from_warehouse_id);
                    $stock->increment('quantity', (float) $item->quantity);
                    $stock->update(['last_movement_at' => now()]);

                    // [P1-F] Symétrique à ship() : la ligne source a été décrémentée à
                    // l'expédition sans être supprimée — elle existe donc forcément
                    // encore ici si un lot avait été renseigné.
                    $sourceLot = null;
                    if (! empty($item->lot_number)) {
                        $sourceLot = StockLot::where('product_id', $item->product_id)
                            ->where('warehouse_id', $transfer->from_warehouse_id)
                            ->where('lot_number', $item->lot_number)
                            ->lockForUpdate()
                            ->first();
                        $sourceLot?->increment('quantity', (float) $item->quantity);
                    }

                    StockMovement::create([
                        'product_id'     => $item->product_id,
                        'warehouse_id'   => $transfer->from_warehouse_id,
                        'type'           => 'entree',
                        'reference_type' => StockTransfer::class,
                        'reference_id'   => $transfer->id,
                        'quantity'       => $item->quantity,
                        'unit_cost'      => $item->unit_cost ?? 0,
                        'total_cost'     => ($item->unit_cost ?? 0) * (float) $item->quantity,
                        'lot_number'     => $item->lot_number,
                        'stock_lot_id'   => $sourceLot?->id,
                        'notes'          => "Transfert {$transfer->number} — ANNULÉ : {$reason}",
                        'created_by'     => Auth::id(),
                        'occurred_at'    => now(),
                    ]);
                }
            }

            $transfer->update([
                'status'       => 'annule',
                'reason'       => $reason,
                'cancelled_by' => Auth::id(),
            ]);

            return $transfer->fresh('items');
        });
    }

    public function delete(StockTransfer $transfer): bool
    {
        if (!$transfer->isDraft()) {
            throw new \RuntimeException("Seul un transfert en brouillon peut être supprimé. Utilisez l'annulation.");
        }
        return $transfer->delete();
    }

    // ───────────────────────────── helpers ─────────────────────────────

    private function syncItems(StockTransfer $transfer, array $items): void
    {
        foreach ($items as $i => $line) {
            if (empty($line['product_id']) || (float) ($line['quantity'] ?? 0) <= 0) continue;

            $transfer->items()->create([
                'product_id'         => $line['product_id'],
                'quantity'           => $line['quantity'],
                'requested_quantity' => $line['requested_quantity'] ?? $line['quantity'],
                'weight'             => $line['weight'] ?? null,
                'volume'             => $line['volume'] ?? null,
                'unit_cost'     => $line['unit_cost']     ?? null,
                'lot_number'    => $line['lot_number']    ?? null,
                'serial_number' => $line['serial_number'] ?? null,
                'expiry_date'   => $line['expiry_date']   ?? null,
                'label'         => $line['label']         ?? null,
                'sort_order'    => $i,
            ]);
        }
    }

    /**
     * [P1-F-C] Résout la ligne ProductStock sous lockForUpdate() ; la crée si
     * absente. Le gap-lock InnoDB posé par le SELECT ... FOR UPDATE (index
     * unique product_id+warehouse_id, isolation REPEATABLE-READ) empêche deux
     * transactions concurrentes de créer chacune leur propre ligne pour le même
     * couple produit+dépôt ; la contrainte unique reste le filet de sécurité
     * ultime en cas d'imprévu (échec explicite, jamais un doublon silencieux).
     */
    private function lockOrCreateProductStock(int $productId, int $warehouseId): ProductStock
    {
        $stock = ProductStock::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        return $stock ?? ProductStock::create([
            'product_id'        => $productId,
            'warehouse_id'      => $warehouseId,
            'quantity'          => 0,
            'reserved_quantity' => 0,
        ]);
    }

    private function fallbackNumber(Company $company): string
    {
        $prefix = 'TRF-' . now()->format('Y');
        $last = StockTransfer::where('company_id', $company->id)
            ->where('number', 'like', $prefix . '-%')
            ->orderByDesc('id')
            ->value('number');
        $seq = $last ? (int) substr($last, strrpos($last, '-') + 1) + 1 : 1;
        return sprintf('%s-%04d', $prefix, $seq);
    }
}
