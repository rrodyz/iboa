<?php

namespace App\Modules\Production\Services;
use App\Services\DocumentSequenceService;

use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * [PRODUCTION] Cycle de vie d'un ordre de fabrication (OF).
 *
 * Workflow : brouillon → lance → en_cours → termine
 *            (annulation possible depuis tout statut non clôturé)
 *
 * La consommation matière, les sorties produits finis et le coût de revient
 * sont gérés en P4/P5 (CoilConsumptionService, ProductionCostService).
 */
class ProductionService
{
    public function __construct(
        private DocumentSequenceService $sequences,
        private ProductionAccountingService $accounting,
    ) {}

    /** Crée un OF en brouillon avec numéro auto + lignes de détail. */
    public function create(array $data, array $lines = []): ProductionOrder
    {
        return DB::transaction(function () use ($data, $lines) {
            $company = currentCompany();

            $data['company_id']     = $company->id;
            $data['fiscal_year_id'] = $company->current_fiscal_year_id;
            $data['number']         = $this->sequences->nextNumber($company, 'ordre_fabrication');
            $data['status']         = 'brouillon';

            $order = ProductionOrder::create($data);
            $this->syncLines($order, $lines);
            $this->recomputeQuantities($order);

            return $order->fresh('lines');
        });
    }

    /** Met à jour un OF éditable (brouillon/lancé) + ses lignes. */
    public function update(ProductionOrder $order, array $data, array $lines = []): ProductionOrder
    {
        if (! $order->isEditable()) {
            throw ValidationException::withMessages(['status' => 'OF non modifiable dans son statut actuel.']);
        }

        return DB::transaction(function () use ($order, $data, $lines) {
            $order->update($data);
            $order->lines()->delete();
            $this->syncLines($order, $lines);
            $this->recomputeQuantities($order);

            return $order->fresh('lines');
        });
    }

    /** brouillon → lance */
    public function launch(ProductionOrder $order): void
    {
        $this->assertStatus($order, 'brouillon');
        $order->update(['status' => 'lance', 'launched_at' => now()]);
    }

    /** lance → en_cours */
    public function start(ProductionOrder $order): void
    {
        $this->assertStatus($order, 'lance');
        $order->update(['status' => 'en_cours']);
    }

    /** en_cours → termine */
    public function finish(ProductionOrder $order): void
    {
        $this->assertStatus($order, 'en_cours');
        $order->update(['status' => 'termine', 'finished_at' => now()]);

        // Pont comptable SYSCOHADA (no-op si désactivé — OFF par défaut)
        $this->accounting->postForOrder($order);
    }

    /** Annulation depuis tout statut non clôturé. */
    public function cancel(ProductionOrder $order, ?string $reason = null): void
    {
        if (in_array($order->status, ['termine', 'annule'], true)) {
            throw ValidationException::withMessages(['status' => 'OF déjà clôturé — annulation impossible.']);
        }

        $note = $order->notes;
        if ($reason) {
            $note = trim(($note ? $note . "\n" : '') . 'Annulé : ' . $reason);
        }
        $order->update(['status' => 'annule', 'notes' => $note]);

        // [V4] Libère les réservations de produit fini liées à cet OF.
        app(ReservationService::class)->releaseForProductionOrder($order);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function assertStatus(ProductionOrder $order, string $expected): void
    {
        if ($order->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => "Transition invalide : l'OF doit être au statut « {$expected} ».",
            ]);
        }
    }

    /** Recrée les lignes de détail (longueur × quantité → mètres). */
    private function syncLines(ProductionOrder $order, array $lines): void
    {
        foreach ($lines as $i => $row) {
            $length   = (float) ($row['length'] ?? 0);
            $quantity = (float) ($row['quantity'] ?? 0);
            if ($length <= 0 && $quantity <= 0) {
                continue;
            }
            $order->lines()->create([
                'length'       => $length,
                'quantity'     => $quantity,
                'total_meters' => round($length * $quantity, 2),
                'unit_id'      => $row['unit_id'] ?? null,
                'label'        => $row['label'] ?? null,
                'sort_order'   => $i,
            ]);
        }
    }

    /** Si des lignes existent, la quantité demandée = somme des quantités. */
    private function recomputeQuantities(ProductionOrder $order): void
    {
        $order->load('lines');
        if ($order->lines->isNotEmpty()) {
            $order->update(['quantity_requested' => $order->lines->sum('quantity')]);
        }
    }
}
