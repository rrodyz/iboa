<?php

namespace App\Modules\Production\Services;

use App\Models\StockLot;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionConsumption;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ReservationService;
use App\Services\StockService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * [PRODUCTION] Consommation de bobines (matière première) sur un OF.
 *
 * Architecture (CDC sync coils/lots 17/07/2026) :
 *   - le LOT apporte la traçabilité (quantité restante en KG) ;
 *   - la BOBINE est l'unité physique (poids restant en KG) ;
 *   - la consommation crée UN SEUL mouvement de sortie économique via le
 *     service central StockService (conversion, product_stocks, idempotence),
 *     portant à la fois stock_lot_id, coil_id et production_consumption_id.
 *
 * L'unité de tenue de stock des matières en bobines est le KILOGRAMME ;
 * une saisie en mètres linéaires est convertie via le facteur kg/ML
 * (bobine > lot > produit — erreur bloquante sans facteur).
 */
class CoilConsumptionService
{
    public function __construct(
        private StockService $stock,
        private CoilCompatibilityService $compatibility,
        private ReservationService $reservations,
    ) {}

    /** Enregistre une consommation de matière depuis une bobine. */
    public function consume(ProductionOrder $order, Coil $coil, float $weight, ?float $length = null, ?string $date = null): ProductionConsumption
    {
        $coil->refresh();

        if (! $order->isInProgress()) {
            throw ValidationException::withMessages(['status' => 'La consommation n\'est possible que sur un OF « en cours ».']);
        }

        // [MTO §9] La bobine doit correspondre à ce que l'OF fabrique : article de
        // la nomenclature, lot, dépôt, société, état, caractéristiques physiques.
        // Vérifié ici plutôt qu'au formulaire pour couvrir tous les canaux, et
        // AVANT la transaction : inutile de verrouiller pour un refus certain.
        $this->compatibility->assertCompatible($order, $coil);

        // Saisie en mètres linéaires sans poids : conversion obligatoire en KG.
        if ($weight <= 0 && $length !== null && $length > 0) {
            $factor = $this->kgPerLinearMeter($coil);
            if ($factor === null) {
                throw ValidationException::withMessages([
                    'weight' => 'Consommation saisie en mètres sans facteur kg/ML : renseignez le facteur sur la bobine, le lot ou le produit.',
                ]);
            }
            $weight = round($length * $factor, 2);
        }

        // [P1-D3] Quantification CANONIQUE unique du poids consommé, à la même
        // échelle que coils.remaining_weight / production_consumptions.
        // weight_consumed (DECIMAL(12,2) — précision physique réelle d'une
        // bobine sur balance industrielle, jamais plus fine que 10 g). Arrondi
        // ICI, une seule fois, AVANT toute écriture : la même valeur alimente
        // ensuite le solde bobine (applyCoilDelta), le mouvement de stock
        // (StockService::recordMovement), le coût ET la consommation
        // d'allocation formelle (ReservationService::recordAllocationConsumption)
        // — jamais un poids brut à 4 décimales d'un côté et arrondi de l'autre
        // (c'était exactement le bug P1-D3 : la réservation se canonisait déjà à
        // 2 décimales en base, mais recevait un delta brut, non aligné avec la
        // bobine physique qui, elle, a toujours été à 2 décimales).
        $weight = round($weight, 2);
        if ($weight <= 0) {
            throw ValidationException::withMessages(['weight' => 'Le poids consommé doit être positif.']);
        }

        // [Qualité #11] Une bobine non libérée par la qualité n'est JAMAIS
        // consommable : QUARANTINED → CONSUMED est interdit. La libération passe
        // obligatoirement par une décision qualité (PurchaseQualityService).
        if ($coil->isQualityBlocked()) {
            throw ValidationException::withMessages([
                'quality' => sprintf(
                    'Bobine %s : statut qualité « %s » — consommation interdite tant que la '
                    . 'qualité ne l\'a pas libérée.',
                    $coil->reference, $coil->quality_status
                ),
            ]);
        }

        // [Qualité #1/#2] Garde QUANTITATIVE : un statut « libéré partiellement »
        // n'autorise rien à lui seul. On compare la demande au solde réellement
        // libéré et non encore consommé/retourné.
        if ($coil->hasQualityBalances()) {
            $availableReleased = $coil->availableReleasedQuantity();
            if ($weight > $availableReleased + 0.001) {
                throw ValidationException::withMessages([
                    'quality' => sprintf(
                        'Bobine %s : %s demandé mais seulement %s libéré et disponible '
                        . '(libéré %s, quarantaine %s). La quarantaine doit être libérée par la qualité.',
                        $coil->reference, $weight, round($availableReleased, 3),
                        (float) $coil->qty_released, (float) ($coil->qty_quarantine ?? 0)
                    ),
                ]);
            }
        }
        if ($weight > (float) $coil->remaining_weight + 0.001) {
            throw ValidationException::withMessages([
                'weight' => 'Poids demandé ('.$weight.' kg) supérieur au restant de la bobine ('.$coil->remaining_weight.' kg).',
            ]);
        }

        if ($coil->valuation_status !== 'valorisation_definitive' || (float) $coil->cost_per_kg <= 0) {
            throw ValidationException::withMessages([
                'cost' => "Bobine {$coil->reference} non valorisée : consommation interdite. Régularisez son coût d’achat avant production.",
            ]);
        }

        return DB::transaction(function () use ($order, $coil, $weight, $length, $date) {
            // Verrous : bobine puis lot (ordre stable → pas d'interblocage).
            $coil = Coil::lockForUpdate()->findOrFail($coil->id);

            // [MTO §9] Recontrôle SOUS VERROU : entre la vérification d'entrée et
            // ici, la bobine a pu être divisée, déplacée, bloquée par la qualité ou
            // réservée à un autre OF. La perdante lit l'état commité par la gagnante.
            $this->compatibility->assertCompatible($order, $coil);

            if ($coil->valuation_status !== 'valorisation_definitive' || (float) $coil->cost_per_kg <= 0) {
                throw ValidationException::withMessages([
                    'cost' => "Bobine {$coil->reference} non valorisée : consommation concurrente refusée.",
                ]);
            }
            if ($weight > (float) $coil->remaining_weight + 0.001) {
                throw ValidationException::withMessages([
                    'weight' => 'Poids demandé supérieur au restant de la bobine (concurrence).',
                ]);
            }
            // [Qualité #2] Recontrôle SOUS VERROU du solde libéré : deux
            // consommations concurrentes ne peuvent pas dépasser ensemble la
            // quantité libérée (la perdante lit l'état commité par la gagnante).
            if ($coil->isQualityBlocked() || ($coil->hasQualityBalances()
                && $weight > $coil->availableReleasedQuantity() + 0.001)) {
                throw ValidationException::withMessages([
                    'quality' => 'Solde libéré par la qualité insuffisant pour cette consommation (concurrence).',
                ]);
            }

            // [P1-D2 — §20 fail-closed] Une bobine ne peut être consommée que si
            // elle est formellement allouée à CET OF (ReservationService::
            // allocateMaterialLot()) — jamais de repli silencieux sur une
            // réservation générique produit+dépôt. recordAllocationConsumption()
            // lève une ValidationException si aucune allocation active ne couvre
            // (production_order_id, coil_id) : AVANT toute écriture physique.
            // Ne s'applique qu'aux bobines d'un article RÉELLEMENT coil-managed
            // (itemCategory.coil_managed) — le périmètre métier exact de P1-D. Une
            // bobine sans product_id, ou dont l'article n'est pas déclaré
            // coil-managed (fixture de coût ad-hoc, jamais rattachée à une vraie
            // catégorie bobine), ne touche déjà jamais product_stocks/stock_lots
            // ou n'est pas le type de matière visé par l'allocation formelle.
            if ($coil->product_id && $coil->product?->isCoilManaged()) {
                $this->reservations->recordAllocationConsumption($order, $coil, $weight);
            }

            $cost = (int) round($weight * (float) $coil->cost_per_kg);

            $consumption = $order->consumptions()->create([
                'company_id' => $order->company_id,
                'coil_id' => $coil->id,
                'weight_consumed' => $weight,
                'length_consumed' => $length ?? 0,
                'cost' => $cost,
                'consumption_source' => 'coil',
                'consumed_at' => $date ?? now(),
                'created_by' => Auth::id(),
            ]);

            // 1. Bobine (unité physique)
            $this->applyCoilDelta($coil, -$weight);

            // 2. Sortie économique UNIQUE : product_stocks + lot + mouvement,
            //    déléguée au service central (verrous, CMP, idempotence).
            //    Un seul mouvement porte lot + bobine + consommation + OF.
            $movement = null;
            if ($coil->product_id && $this->stockWarehouseId($coil, $order)) {
                $factor = $this->kgPerLinearMeter($coil);
                $movement = $this->stock->recordMovement([
                    'product_id' => $coil->product_id,
                    'warehouse_id' => $this->stockWarehouseId($coil, $order),
                    'type' => 'sortie',
                    // Saisie opérationnelle conservée (ML si fournie, sinon KG)…
                    'quantity' => ($length && $length > 0) ? $length : $weight,
                    'uom' => ($length && $length > 0) ? 'ML' : 'KG',
                    'conversion_factor' => ($length && $length > 0) ? $factor : 1,
                    // …mais le stock est TOUJOURS mû en kilogrammes.
                    'quantity_in_stock_uom' => $weight,
                    'stock_uom' => 'KG',
                    'unit_cost' => (float) $coil->cost_per_kg,
                    'stock_lot_id' => $coil->stock_lot_id,
                    'coil_id' => $coil->id,
                    'production_order_id' => $order->id,
                    'production_consumption_id' => $consumption->id,
                    'reference_type' => ProductionOrder::class,
                    'reference_id' => $order->id,
                    'notes' => "Consommation bobine {$coil->reference} — OF {$order->number}",
                    'idempotency_key' => 'coil-consumption:'.$consumption->id,
                    // La matière physique est déjà sortie : le stock théorique
                    // ne doit jamais bloquer la déclaration de consommation.
                    'allow_negative' => true,
                ]);

                $consumption->update(['stock_movement_id' => $movement->id]);
            }

            return $consumption->fresh();
        });
    }

    /**
     * Annule une consommation : mouvement INVERSE (le mouvement initial reste
     * consultable), restitution bobine + lot + product_stocks, consommation
     * marquée « reversed » — jamais supprimée.
     */
    public function reverse(ProductionConsumption $consumption, ?string $reason = null): void
    {
        $order = $consumption->productionOrder;
        if ($order && ! $order->isInProgress()) {
            throw ValidationException::withMessages(['status' => 'Annulation impossible : l\'OF n\'est plus « en cours ».']);
        }
        if ($consumption->reversed_at) {
            throw ValidationException::withMessages(['consumption' => 'Cette consommation est déjà annulée.']);
        }

        DB::transaction(function () use ($consumption, $reason) {
            $consumption = ProductionConsumption::lockForUpdate()->findOrFail($consumption->id);
            if ($consumption->reversed_at) {
                return; // déjà annulée par une requête concurrente
            }

            $weight = (float) $consumption->weight_consumed;

            if ($coil = $consumption->coil) {
                $this->applyCoilDelta($coil, $weight);

                if ($consumption->stock_movement_id && $coil->product_id) {
                    $original = $consumption->stockMovement;
                    $this->stock->recordMovement([
                        'product_id' => $coil->product_id,
                        'warehouse_id' => $original?->warehouse_id ?? $coil->warehouse_id,
                        'type' => 'entree',
                        'quantity' => $original?->quantity ?? $weight,
                        'uom' => $original?->uom ?? 'KG',
                        'conversion_factor' => $original?->conversion_factor ?? 1,
                        'quantity_in_stock_uom' => $weight,
                        'stock_uom' => 'KG',
                        'unit_cost' => (float) $coil->cost_per_kg,
                        'stock_lot_id' => $coil->stock_lot_id,
                        'coil_id' => $coil->id,
                        'production_order_id' => $consumption->production_order_id,
                        'production_consumption_id' => $consumption->id,
                        'reversal_of_movement_id' => $consumption->stock_movement_id,
                        'notes' => 'Extourne consommation bobine'.($reason ? " — {$reason}" : ''),
                        'idempotency_key' => 'coil-consumption-reversal:'.$consumption->id,
                    ]);
                }
            }

            $consumption->update([
                'reversed_at' => now(),
                'reversed_by' => Auth::id(),
            ]);
        });
    }

    /**
     * Facteur kg par mètre linéaire — ordre de priorité strict :
     * 1. explicite bobine, 2. explicite lot, 3. explicite article,
     * 4. déduction physique (poids initial / longueur estimée),
     * 5. fallback géométrique (largeur × épaisseur bobine × densité article),
     * 6. null si rien d'exploitable (l'appelant décide si c'est bloquant :
     *    obligatoire pour une saisie en ML, facultatif sinon).
     */
    public function kgPerLinearMeter(Coil $coil): ?float
    {
        $candidates = [
            (float) ($coil->kg_per_linear_meter ?? 0),
            (float) ($coil->stock_lot_id ? StockLot::find($coil->stock_lot_id)?->kg_per_linear_meter ?? 0 : 0),
            (float) ($coil->product?->kg_per_linear_meter ?? 0),
        ];
        foreach ($candidates as $factor) {
            if ($factor > 0) {
                return $factor;
            }
        }
        // 4. Déduction physique : poids initial connu / longueur estimée connue.
        if ((float) $coil->estimated_length > 0 && (float) $coil->initial_weight > 0) {
            return round((float) $coil->initial_weight / (float) $coil->estimated_length, 4);
        }

        // 5. Fallback géométrique — largeur × épaisseur DE LA BOBINE (mm, jamais
        //    l'article fini : une bobine de 1250mm produit un utile de 1000mm après
        //    refente) × densité matière (kg/dm³, cf. ArticlesSageSeeder : acier=7.850).
        //    kg/m = largeur_mm × épaisseur_mm × densité_kg/dm³ / 1000.
        $width     = (float) ($coil->width ?? 0);
        $thickness = (float) ($coil->thickness ?? 0);
        $density   = (float) ($coil->product?->density ?? 0);
        if ($width > 0 && $thickness > 0 && $density > 0) {
            return round($width * $thickness * $density / 1000, 4);
        }

        // 6. Aucune donnée exploitable.
        return null;
    }

    /** Dépôt de tenue de stock de la matière : bobine, sinon dépôt MP de l'OF. */
    private function stockWarehouseId(Coil $coil, ProductionOrder $order): ?int
    {
        return $coil->warehouse_id
            ?? $order->depot_matiere_id
            ?? null;
    }

    /** Applique une variation de poids à la bobine et resynchronise son statut. */
    private function applyCoilDelta(Coil $coil, float $delta): void
    {
        $coil = Coil::lockForUpdate()->find($coil->id);
        $remaining = max(0, round((float) $coil->remaining_weight + $delta, 2));

        $status = match (true) {
            $remaining <= 0.001 => 'epuisee',
            $remaining < (float) $coil->initial_weight => 'en_production',
            default => 'disponible',
        };

        $coil->update(['remaining_weight' => $remaining, 'status' => $status]);
    }
}
