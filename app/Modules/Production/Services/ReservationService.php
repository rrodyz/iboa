<?php

namespace App\Modules\Production\Services;

use App\Models\Order;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockReservation;
use App\Models\Warehouse;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * [PRODUCTION ↔ VENTES/STOCK] Réservation du produit fini fabriqué pour le
 * client de la commande liée à l'OF. Bump `product_stocks.reserved_quantity`
 * → la quantité disponible (quantity − reserved) exclut le PF promis au client.
 */
class ReservationService
{
    public function __construct(
        private CoilCompatibilityService $compatibility,
    ) {}

    /** Réserve le produit fini de l'OF terminé pour son client. */
    public function reserveForOrder(ProductionOrder $order): StockReservation
    {
        if ($order->status !== 'termine') {
            throw ValidationException::withMessages(['status' => 'Réservation possible seulement sur un OF terminé.']);
        }
        if (! $order->product_id) {
            throw ValidationException::withMessages(['product' => 'Aucun produit fini défini sur l\'OF.']);
        }

        // On réserve strictement le produit fini RÉELLEMENT fabriqué (en stock).
        // Pas de repli sur quantity_requested : réserver une quantité non produite
        // créerait une réservation sans stock en face (dispo négative).
        $qty = (float) $order->quantity_produced;
        if ($qty <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantité produite nulle — rien à réserver.']);
        }

        if (StockReservation::where('production_order_id', $order->id)->where('status', 'reserved')->exists()) {
            throw ValidationException::withMessages(['status' => 'Le produit fini de cet OF est déjà réservé.']);
        }

        // [FIX réservation fantôme] Un OF clôturé APRÈS la livraison (visa tardif)
        // ne doit pas réserver un PF déjà parti chez le client : la commande
        // livrée/facturée/annulée n'a plus rien à réserver, et une commande
        // partiellement livrée n'a besoin que du reliquat.
        if ($order->order_id) {
            $salesOrder = Order::with('items')->find($order->order_id);
            if ($salesOrder) {
                if (in_array($salesOrder->status, ['livre', 'facture', 'annule'], true)) {
                    throw ValidationException::withMessages(['order' => sprintf(
                        'La commande %s est « %s » — le produit fini a déjà été livré, aucune réservation à créer.',
                        $salesOrder->number, $salesOrder->status
                    )]);
                }
                // Le plafond au reliquat ne vaut que si la commande porte des
                // lignes du produit (sans ligne, impossible de calculer un
                // reliquat — comportement historique conservé).
                $lignesProduit = $salesOrder->items->where('product_id', $order->product_id);
                if ($lignesProduit->isNotEmpty()) {
                    $resteALivrer = (float) $lignesProduit
                        ->sum(fn ($i) => max(0, (float) $i->quantity - (float) $i->delivered_quantity));
                    $dejaReserve = (float) StockReservation::where('order_id', $salesOrder->id)
                        ->where('product_id', $order->product_id)
                        ->where('status', 'reserved')
                        ->sum('quantity');
                    $resteAReserver = max(0, $resteALivrer - $dejaReserve);
                    if ($resteAReserver <= 0) {
                        throw ValidationException::withMessages(['order' => 'Les quantitÃ©s de la commande sont dÃ©jÃ entiÃ¨rement livrÃ©es ou rÃ©servÃ©es â€” aucune rÃ©servation Ã crÃ©er.']);
                    }
                    $qty = min($qty, $resteAReserver);
                }
            }
        }

        $output = $order->outputs()->whereNotNull('warehouse_id')->latest('id')->first();
        $warehouseId = ($output?->quality_released_at && $output->release_warehouse_id
                ? $output->release_warehouse_id
                : $output?->warehouse_id)
            ?? Warehouse::where('company_id', $order->company_id)->orderByDesc('is_default')->value('id');

        return DB::transaction(function () use ($order, $qty, $warehouseId) {
            $reservation = StockReservation::create([
                'company_id' => $order->company_id,
                'order_id' => $order->order_id,
                'production_order_id' => $order->id,
                'product_id' => $order->product_id,
                'warehouse_id' => $warehouseId,
                'quantity' => $qty,
                'status' => 'reserved',
                'reserved_at' => now(),
                'created_by' => Auth::id(),
            ]);

            $this->adjustReserved($order->product_id, $warehouseId, $qty);

            return $reservation;
        });
    }

    /**
     * Réserve le produit fini DISPONIBLE EN STOCK pour les lignes d'une commande
     * (réservation directe stock, sans OF). Répartit sur les entrepôts disposant
     * de stock. Retourne la quantité totale réservée.
     */
    public function reserveStockForOrder(Order $order): float
    {
        // [GARDE anti-résa fantôme] Un OrderConfirmed ré-émis (re-validation
        // workflow) sur une commande déjà livrée/facturée/annulée re-réservait
        // du stock jamais libéré ensuite (cas réel : CMD-2026-050 re-réservée
        // 2 h après la validation de son BL).
        if (in_array($order->status, ['livre', 'facture', 'annule'], true)) {
            return 0.0;
        }

        $analysis = app(SalesProductionService::class)->stockAnalysis($order);
        $totalReserved = 0.0;

        DB::transaction(function () use ($order, $analysis, &$totalReserved) {
            foreach ($analysis['lines'] as $line) {
                $need = (float) $line['reservable'];
                if ($need <= 0) {
                    continue;
                }

                $stocks = ProductStock::where('product_id', $line['product_id'])
                    ->whereRaw('(quantity - reserved_quantity) > 0')
                    ->orderByRaw('(quantity - reserved_quantity) DESC')
                    ->lockForUpdate()->get();

                foreach ($stocks as $stock) {
                    if ($need <= 0) {
                        break;
                    }
                    $avail = (float) $stock->quantity - (float) $stock->reserved_quantity;
                    $take = min($need, $avail);
                    if ($take <= 0) {
                        continue;
                    }

                    StockReservation::create([
                        'company_id' => $order->company_id,
                        'order_id' => $order->id,
                        'product_id' => $line['product_id'],
                        'warehouse_id' => $stock->warehouse_id,
                        'quantity' => $take,
                        'status' => 'reserved',
                        'reserved_at' => now(),
                        'created_by' => Auth::id(),
                    ]);
                    $stock->update(['reserved_quantity' => (float) $stock->reserved_quantity + $take]);

                    $need -= $take;
                    $totalReserved += $take;
                }
            }
        });

        return $totalReserved;
    }

    /**
     * [FIX A4 — rapport de test MTO] Réservation FERME de la matière première à
     * l'allocation de l'OF : pour chaque composant de la nomenclature suivi en
     * product_stocks, réserve min(besoin théorique, disponible) au dépôt de sortie.
     * Le besoin = quantité demandée × qté/m × (1 + taux de perte). La réservation
     * (production_order_id, sans order_id → aucune interaction avec les réservations
     * de vente) bloque la même matière pour un autre OF ; elle est libérée au
     * backflush de la déclaration, à la clôture ou à l'annulation de l'OF.
     * Retourne la quantité totale réservée. Idempotent par produit/OF.
     */
    public function reserveMaterialsForOrder(ProductionOrder $order): float
    {
        $order->loadMissing('billOfMaterial.lines.product');
        $bom = $order->billOfMaterial;
        if (! $bom) {
            return 0.0;
        }

        $qty = (float) $order->quantity_requested;
        $totalReserved = 0.0;

        DB::transaction(function () use ($order, $bom, $qty, &$totalReserved) {
            foreach ($bom->lines as $line) {
                $product = $line->product;
                $per = (float) $line->quantity_per_meter;
                if (! $product || $per <= 0 || $qty <= 0) {
                    continue;
                }

                // [P1-D] Une matière suivie par lot ou bobine EXIGE une allocation
                // formelle (ReservationService::allocateMaterialLot(), sélection
                // explicite du lot/bobine) — jamais la réservation générique
                // produit+dépôt ci-dessous, qui créerait un double niveau de
                // réservation pour le même besoin (ex. 1200 générique + 1000+200
                // par lot = 2400 réservé pour 1200 de besoin réel).
                if ($product->isCoilManaged() || (bool) $product->has_lot_number) {
                    continue;
                }

                // Idempotence : matière déjà réservée pour ce produit sur cet OF.
                if (StockReservation::where('production_order_id', $order->id)
                    ->where('product_id', $product->id)
                    ->where('status', 'reserved')->exists()) {
                    continue;
                }

                $need = round($per * $qty * (1 + (float) ($line->waste_rate ?? 0) / 100), 4);

                $stockQuery = ProductStock::where('product_id', $product->id)
                    ->whereRaw('(quantity - reserved_quantity) > 0');
                if ($line->depot_sortie_id) {
                    $stockQuery->where('warehouse_id', $line->depot_sortie_id);
                }
                $stock = $stockQuery->orderByRaw('(quantity - reserved_quantity) DESC')->lockForUpdate()->first();
                if (! $stock) {
                    continue; // pas de stock suivi (ex. bobine hors product_stocks) — signalé par materialShortages
                }

                $avail = (float) $stock->quantity - (float) $stock->reserved_quantity;
                $take = min($need, $avail);
                if ($take <= 0) {
                    continue;
                }

                StockReservation::create([
                    'company_id' => $order->company_id,
                    'production_order_id' => $order->id,
                    'product_id' => $product->id,
                    'warehouse_id' => $stock->warehouse_id,
                    'quantity' => $take,
                    'status' => 'reserved',
                    'reserved_at' => now(),
                    'created_by' => Auth::id(),
                ]);
                $stock->update(['reserved_quantity' => (float) $stock->reserved_quantity + $take]);
                $totalReserved += $take;
            }
        });

        return $totalReserved;
    }

    /**
     * [P1-D1] Allocation FORMELLE d'un lot (ou d'une bobine précise) de matière
     * première à un OF — sélection EXPLICITE par l'appelant (pas de FIFO/FEFO
     * automatique dans cette passe : à clarifier métier). Une seule réservation
     * ÉCONOMIQUE peut se ventiler en plusieurs lignes physiques (plusieurs appels
     * = plusieurs lots/bobines pour le même besoin) — jamais de réservation
     * générique produit+dépôt en parallèle pour la même matière (voir le garde
     * ajouté dans reserveMaterialsForOrder()).
     *
     * Verrouillage : bobine (si fournie), PUIS ProductStock, PUIS lot — ordre
     * global cohérent avec CoilConsumptionService::consume() (Coil → ProductStock
     * → StockLot) ET avec StockTransferService::ship()/receive()/cancel()
     * (ProductStock → StockLot), qui n'a jamais connaissance de la bobine.
     *
     * @throws ValidationException
     */
    public function allocateMaterialLot(ProductionOrder $order, StockLot $stockLot, float $quantity, ?Coil $coil = null): StockReservation
    {
        // [P1-D3] Quantification CANONIQUE unique, avant toute écriture : la même
        // valeur (arrondie à l'échelle réelle de stock_reservations, 2 décimales —
        // cf. matrice de précision P1-D3, alignée sur la précision physique réelle
        // des bobines/consommations, déjà à 2 décimales bien avant P1-D) sert à la
        // fois pour StockReservation.quantity ET pour le delta ProductStock.
        // reserved_quantity. Avant ce correctif, la réservation stockait la valeur
        // arrondie (cast decimal:2) tandis que l'agrégat recevait la valeur BRUTE
        // (jusqu'à 4 décimales) : les deux représentations divergeaient dès
        // l'allocation et ne se rejoignaient plus jamais (bug P1-D3 prouvé par
        // tests réels — cf. rapport P1-D ABSOLUTE FINAL GATE).
        $quantity = $this->canonicalizeQuantity($quantity);
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La quantité à allouer doit être positive.']);
        }

        return DB::transaction(function () use ($order, $stockLot, $quantity, $coil) {
            // [P1-D QA gate — ordre global des verrous] Bobine (si fournie), PUIS
            // ProductStock, PUIS StockLot. ProductStock avant StockLot — jamais
            // l'inverse — pour rester cohérent avec StockTransferService::ship()/
            // receive()/cancel() (verrouillent toujours ProductStock avant
            // StockLot) et avec CoilConsumptionService::consume() (Coil, puis
            // ProductStock via recordAllocationConsumption()->adjustReserved(),
            // puis StockLot via StockService::recordMovement()). L'ordre PRÉCÉDENT
            // (StockLot avant ProductStock, ce dernier verrouillé seulement à la
            // fin dans adjustReserved()) inversait l'ordre de ship()/receive() :
            // un allocateMaterialLot() et un ship() concurrents sur le même
            // produit+dépôt+lot pouvaient s'attendre mutuellement — deadlock
            // structurel prouvé par lecture de code (jamais observé en usage
            // normal aujourd'hui, mais un vrai risque dès que les deux flux
            // peuvent réellement se croiser). Le verrou ci-dessous n'altère rien :
            // il pose juste le verrou plus tôt sur la ligne que adjustReserved()
            // verrouillera de toute façon en fin de méthode (ré-acquisition sans
            // effet dans la même transaction).
            if ($coil) {
                $coil = Coil::lockForUpdate()->findOrFail($coil->id);
            }
            if ($stockLot->product_id && $stockLot->warehouse_id) {
                ProductStock::where('product_id', $stockLot->product_id)
                    ->where('warehouse_id', $stockLot->warehouse_id)
                    ->lockForUpdate()
                    ->first();
            }
            $stockLot = StockLot::lockForUpdate()->findOrFail($stockLot->id);

            $product = $stockLot->product;
            if (! $product) {
                throw ValidationException::withMessages(['stock_lot_id' => 'Lot sans article rattaché.']);
            }

            // Le lot doit correspondre à un composant de la nomenclature de l'OF.
            $authorized = $this->compatibility->authorizedComponentIds($order);
            if ($authorized !== null && ! in_array((int) $product->id, $authorized, true)) {
                throw ValidationException::withMessages([
                    'product_id' => sprintf('L’article #%d du lot ne figure pas parmi les composants de la nomenclature de l’OF.', $product->id),
                ]);
            }

            // Dépôt : le lot doit être au dépôt matière attendu par l'OF (sinon un
            // transfert P1-F est requis avant allocation — on ne le déclenche jamais
            // ici implicitement, cf. périmètre P1-D).
            if ($order->depot_matiere_id && (int) $stockLot->warehouse_id !== (int) $order->depot_matiere_id) {
                throw ValidationException::withMessages([
                    'warehouse_id' => sprintf('Le lot est au dépôt #%d, l’OF attend le dépôt matière #%d — effectuez un transfert avant allocation.', $stockLot->warehouse_id, $order->depot_matiere_id),
                ]);
            }

            if ($coil) {
                if ((int) $coil->product_id !== (int) $product->id) {
                    throw ValidationException::withMessages(['coil_id' => 'La bobine ne porte pas le même article que le lot.']);
                }
                if ((int) $coil->stock_lot_id !== (int) $stockLot->id) {
                    throw ValidationException::withMessages(['coil_id' => 'La bobine n’appartient pas au lot indiqué.']);
                }
                if ($order->depot_matiere_id && $coil->warehouse_id && (int) $coil->warehouse_id !== (int) $order->depot_matiere_id) {
                    throw ValidationException::withMessages(['coil_id' => 'La bobine n’est pas au dépôt matière attendu par l’OF.']);
                }
                if ($coil->isQualityBlocked()) {
                    throw ValidationException::withMessages([
                        'quality' => sprintf('Bobine %s : statut qualité « %s » — allocation interdite (même règle que la consommation).', $coil->reference, $coil->quality_status),
                    ]);
                }
            } elseif ($product->isCoilManaged()) {
                // [§12] Une matière coil-managed exige une bobine précise : le
                // niveau lot seul ne désigne pas une unité physique consommable.
                throw ValidationException::withMessages(['coil_id' => 'Cet article est géré par bobine — indiquez la bobine à allouer, pas seulement le lot.']);
            }

            // Disponibilité SOUS VERROU : bobine si fournie (granularité physique
            // la plus précise), sinon lot. Jamais les deux niveaux indépendamment.
            if ($coil) {
                $reservedOnCoil = (float) StockReservation::where('coil_id', $coil->id)
                    ->where('status', 'reserved')->get()->sum(fn ($r) => $r->remainingReserved());
                $available = (float) $coil->remaining_weight - $reservedOnCoil;
                if ($quantity > $available + 0.0001) {
                    throw ValidationException::withMessages([
                        'quantity' => sprintf('Bobine %s : disponible %s, demandé %s.', $coil->reference, number_format($available, 2, ',', ' '), number_format($quantity, 2, ',', ' ')),
                    ]);
                }
            } else {
                $reservedOnLot = (float) StockReservation::where('stock_lot_id', $stockLot->id)
                    ->where('status', 'reserved')->get()->sum(fn ($r) => $r->remainingReserved());
                $available = (float) $stockLot->quantity - $reservedOnLot;
                if ($quantity > $available + 0.0001) {
                    throw ValidationException::withMessages([
                        'quantity' => sprintf('Lot %s : disponible %s, demandé %s.', $stockLot->lot_number, number_format($available, 2, ',', ' '), number_format($quantity, 2, ',', ' ')),
                    ]);
                }
            }

            $reservation = StockReservation::create([
                'company_id' => $order->company_id,
                'production_order_id' => $order->id,
                'product_id' => $product->id,
                'warehouse_id' => $stockLot->warehouse_id,
                'stock_lot_id' => $stockLot->id,
                'coil_id' => $coil?->id,
                'quantity' => $quantity,
                'consumed_quantity' => 0,
                'status' => 'reserved',
                'reserved_at' => now(),
                'created_by' => Auth::id(),
            ]);

            $this->adjustReserved($product->id, $stockLot->warehouse_id, $quantity);

            return $reservation;
        });
    }

    /**
     * [P1-D2] Appelée par CoilConsumptionService::consume() à chaque
     * consommation réelle : réduit le reste réservé de l'allocation
     * correspondante (jamais toute la ligne d'un coup si consommation
     * partielle) et répercute la baisse sur product_stocks.reserved_quantity
     * via le même adjustReserved() que tout le reste du service — propriétaire
     * unique de l'écriture, aucun second chemin.
     *
     * @throws ValidationException si aucune allocation active ne couvre cette
     *                              consommation (fail-closed — §20 : jamais de
     *                              repli silencieux sur une réservation générique)
     */
    public function recordAllocationConsumption(ProductionOrder $order, Coil $coil, float $weight): StockReservation
    {
        // [P1-D3] Même quantification canonique qu'à l'allocation (cf.
        // allocateMaterialLot()) : consumed_quantity et le delta ProductStock
        // doivent porter EXACTEMENT la même valeur que celle comparée à
        // remainingReserved() (elle-même dérivée des colonnes canoniques
        // quantity/consumed_quantity, 2 décimales). Sans ce recadrage, comparer
        // un poids brut (jusqu'à 4 décimales) à une réservation stockée à 2
        // décimales laissait un résidu qui empêchait le statut de passer à
        // « consumed » même après consommation intégrale (bug P1-D3).
        $weight = $this->canonicalizeQuantity($weight);

        return DB::transaction(function () use ($order, $coil, $weight) {
            $reservation = StockReservation::where('production_order_id', $order->id)
                ->where('coil_id', $coil->id)
                ->where('status', 'reserved')
                ->lockForUpdate()
                ->first();

            if (! $reservation) {
                throw ValidationException::withMessages([
                    'coil_id' => sprintf('Aucune allocation active de la bobine %s à l’OF %s — consommation refusée.', $coil->reference, $order->number),
                ]);
            }

            $remaining = $reservation->remainingReserved();
            if ($weight > $remaining + 0.0001) {
                throw ValidationException::withMessages([
                    'weight' => sprintf('Allocation bobine %s : reste réservé %s, consommation demandée %s.', $coil->reference, number_format($remaining, 2, ',', ' '), number_format($weight, 2, ',', ' ')),
                ]);
            }

            $newConsumed = (float) $reservation->consumed_quantity + $weight;
            $stillReserved = max(0.0, (float) $reservation->quantity - $newConsumed);

            $reservation->update([
                'consumed_quantity' => $newConsumed,
                'status' => $stillReserved <= 0.0001 ? 'consumed' : 'reserved',
            ]);

            $this->adjustReserved($reservation->product_id, $reservation->warehouse_id, -$weight);

            return $reservation->fresh();
        });
    }

    /**
     * [FIX A4] Libère les réservations MATIÈRE actives d'un OF — toutes, ou celles
     * d'un produit donné (appelé avant le backflush pour que la propre réservation
     * de l'OF ne bloque pas sa sortie de composant).
     */
    public function releaseMaterialReservations(ProductionOrder $order, ?int $productId = null): int
    {
        return $this->releaseMany(
            StockReservation::where('production_order_id', $order->id)
                ->where('status', 'reserved')
                ->when($productId, fn ($q) => $q->where('product_id', $productId))
                // Jamais la réservation du produit fini (posée à la clôture pour le client).
                ->when($order->product_id, fn ($q) => $q->where('product_id', '!=', $order->product_id))
                ->get()
        );
    }

    /** Libère toutes les réservations actives d'une commande (annulation commande). */
    public function releaseForOrder(Order $order): int
    {
        return $this->releaseMany(
            StockReservation::where('order_id', $order->id)->where('status', 'reserved')->get()
        );
    }

    /** Libère toutes les réservations actives d'un OF (annulation production). */
    public function releaseForProductionOrder(ProductionOrder $order): int
    {
        return $this->releaseMany(
            StockReservation::where('production_order_id', $order->id)->where('status', 'reserved')->get()
        );
    }

    private function releaseMany(Collection $reservations): int
    {
        $n = 0;
        foreach ($reservations as $r) {
            $this->release($r);
            $n++;
        }

        return $n;
    }

    /** Libère une réservation (restitue la disponibilité). */
    public function release(StockReservation $reservation): void
    {
        if ($reservation->status !== 'reserved') {
            throw ValidationException::withMessages(['status' => 'Réservation déjà libérée ou consommée.']);
        }

        DB::transaction(function () use ($reservation) {
            // [P1-D2 — §9] Ne jamais libérer plus que le reste réellement réservé :
            // une allocation partiellement consommée (recordAllocationConsumption())
            // a déjà réduit product_stocks.reserved_quantity pour la part consommée.
            // Pour toute réservation SANS consommation partielle (vente, produit fini,
            // matière non lotée — consumed_quantity=0 par défaut), remainingReserved()
            // == quantity : comportement strictement inchangé.
            $this->adjustReserved($reservation->product_id, $reservation->warehouse_id, -$reservation->remainingReserved());
            $reservation->update(['status' => 'released', 'released_at' => now()]);
        });
    }

    /**
     * [P1-D3] Point UNIQUE de quantification des quantités matière allouées/
     * consommées. Échelle = celle réellement portée par stock_reservations.
     * quantity/consumed_quantity (DECIMAL(14,2)) — qui coïncide avec la
     * précision physique réelle des bobines (coils.remaining_weight,
     * production_consumptions.weight_consumed, toutes deux DECIMAL(12,2)
     * depuis bien avant P1-D). Le besoin BOM brut (jusqu'à 4 décimales,
     * bill_of_material_lines à DECIMAL(12,4)) reste un calcul théorique ; la
     * quantité OPÉRATIONNELLE de stock ne l'a jamais été à plus de 2 décimales
     * dans ce système. Un seul appel ici — jamais round($x, 2) recopié
     * ailleurs dans ce service.
     */
    private function canonicalizeQuantity(float $raw): float
    {
        return round($raw, 2);
    }

    private function adjustReserved(int $productId, ?int $warehouseId, float $delta): void
    {
        if (! $warehouseId) {
            return;
        }
        $stock = ProductStock::firstOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['quantity' => 0, 'reserved_quantity' => 0, 'avg_cost' => 0],
        );
        $stock = ProductStock::lockForUpdate()->find($stock->id);
        $new = max(0, (float) $stock->reserved_quantity + $delta);
        $stock->update(['reserved_quantity' => $new]);
    }
}
