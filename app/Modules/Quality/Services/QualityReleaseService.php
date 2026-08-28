<?php

namespace App\Modules\Quality\Services;

use App\Modules\Production\Models\ProductionBatch;
use App\Modules\Quality\Models\QualityRelease;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * [QUA-07] Workflow de libération qualité des lots de fabrication.
 *  - libere      : lot conforme, disponible (batch → conforme)
 *  - refuse      : lot bloqué en quarantaine (batch reste en_cours)
 *  - derogation  : libéré sous dérogation avec référence (batch → conforme)
 */
class QualityReleaseService
{
    public function __construct(private StockService $stock) {}

    /**
     * Enregistre une décision de libération qualité pour un lot.
     *
     * @param  string  $status  libere|refuse|derogation
     */
    public function decide(ProductionBatch $batch, string $status, ?string $comment = null, ?string $derogationRef = null, ?int $controlPlanId = null): QualityRelease
    {
        return DB::transaction(function () use ($batch, $status, $comment, $derogationRef, $controlPlanId) {
            $order = $batch->productionOrder;
            if ($status === 'libere' && $order?->controle_qualite_obligatoire
                && $order->qualityControls()->latest('id')->value('status') !== 'conforme') {
                throw ValidationException::withMessages([
                    'decision' => 'Libération impossible : aucun contrôle qualité conforme ne couvre ce lot.',
                ]);
            }
            $release = QualityRelease::updateOrCreate(
                ['production_batch_id' => $batch->id],
                [
                    'company_id' => $batch->company_id,
                    'control_plan_id' => $controlPlanId,
                    'reference' => $this->referenceFor($batch),
                    'quantity' => $batch->quantity,
                    'status' => $status,
                    'decision_comment' => $comment,
                    'derogation_reference' => $status === 'derogation' ? $derogationRef : null,
                    'decided_by' => auth()->id(),
                    'decided_at' => now(),
                    'created_by' => $batch->qualityRelease?->created_by ?? auth()->id(),
                ]
            );

            // Répercussion sur le statut du lot (sémantique existante en_cours→conforme→cloture).
            if (in_array($status, ['libere', 'derogation'], true)) {
                $this->releaseFinishedGoods($batch);
                if ($batch->status === 'en_cours') {
                    $batch->update(['status' => 'conforme']);
                }
            } elseif ($status === 'refuse') {
                // Lot bloqué : on annule une éventuelle conformité antérieure.
                if ($batch->status === 'conforme') {
                    $batch->update(['status' => 'en_cours']);
                }

                // [P4 — faille prouvée] Un refus qui suit une libération
                // antérieure (revirement qualité) laissait quality_released_at/
                // _by intacts sur les sorties déjà libérées : ProductionDeliveryGuard
                // lit CE champ (pas le statut QualityRelease) pour juger un article
                // livrable — une commande pouvait donc encore être livrée après un
                // refus qualité explicite. On révoque ici la marque de libération
                // au niveau des sorties, seule source lue par la garde de
                // livraison. Le mouvement de stock déjà exécuté vers le dépôt de
                // libération n'est PAS inversé automatiquement — un refus après
                // libération est un événement rare (revirement qualité) qui
                // implique une action physique (rappel de matière), hors
                // périmètre d'une correction logicielle silencieuse.
                $this->revokeRelease($batch);
            }

            return $release;
        });
    }

    /** [P4] Révoque la marque de libération qualité sur les sorties déjà libérées de l'OF du lot. */
    private function revokeRelease(ProductionBatch $batch): void
    {
        $batch->productionOrder?->outputs()
            ->whereNotNull('quality_released_at')
            ->lockForUpdate()
            ->get()
            ->each(fn ($output) => $output->update(['quality_released_at' => null, 'quality_released_by' => null]));
    }

    private function releaseFinishedGoods(ProductionBatch $batch): void
    {
        $outputs = $batch->productionOrder?->outputs()
            ->whereNotNull('release_warehouse_id')
            ->whereNull('quality_released_at')
            ->lockForUpdate()
            ->get() ?? collect();

        foreach ($outputs as $output) {
            $this->stock->recordMovement([
                'product_id' => $output->product_id,
                'warehouse_id' => $output->warehouse_id,
                'dest_warehouse_id' => $output->release_warehouse_id,
                'type' => 'transfert',
                'quantity' => (float) $output->quantity,
                'unit_cost' => (float) ($output->stockMovement?->unit_cost ?? 0),
                'idempotency_key' => 'quality-release-output:'.$output->id,
                'reference_type' => ProductionBatch::class,
                'reference_id' => $batch->id,
                'notes' => 'Libération qualité lot '.$batch->batch_number,
            ]);
            $output->update([
                'quality_released_at' => now(),
                'quality_released_by' => auth()->id(),
            ]);
        }
    }

    private function referenceFor(ProductionBatch $batch): string
    {
        return 'LIB-'.($batch->batch_number ?: $batch->id);
    }
}
