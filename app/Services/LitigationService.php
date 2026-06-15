<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\LitigationCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [TRESO] Dossiers de contentieux : création, suivi du stade/statut, et passage
 * en perte (irrécouvrable) avec écriture comptable 6514 / 411.
 */
class LitigationService
{
    public function __construct(
        private DocumentSequenceService $seq,
        private AccountingService $accountingService,
    ) {}

    public function create(array $data): LitigationCase
    {
        return DB::transaction(function () use ($data) {
            $company = Company::findOrFail(Auth::user()->company_id);

            // [SÉCURITÉ] La facture (si fournie) doit appartenir au client.
            if (!empty($data['invoice_id'])) {
                $belongs = Invoice::where('id', $data['invoice_id'])
                    ->where('client_id', $data['client_id'])->exists();
                if (!$belongs) {
                    throw new \RuntimeException("La facture sélectionnée n'appartient pas à ce client.");
                }
            }

            return LitigationCase::create([
                'company_id' => $company->id,
                'client_id'  => (int) $data['client_id'],
                'invoice_id' => $data['invoice_id'] ?? null,
                'number'     => $this->seq->nextNumber($company, 'contentieux'),
                'amount'     => (int) $data['amount'],
                'stage'      => $data['stage'] ?? 'mise_en_demeure',
                'status'     => 'ouvert',
                'opened_at'  => $data['opened_at'] ?? today(),
                'notes'      => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);
        });
    }

    /**
     * Met à jour le stade et/ou le statut. Le passage à « irrecouvrable »
     * déclenche l'écriture de perte (6514/411) une seule fois.
     *
     * @throws \RuntimeException
     */
    public function update(LitigationCase $case, array $data): LitigationCase
    {
        return DB::transaction(function () use ($case, $data) {
            $case = LitigationCase::lockForUpdate()->findOrFail($case->id);

            $newStatus = $data['status'] ?? $case->status;
            $closing   = in_array($newStatus, ['recouvre', 'irrecouvrable'], true);

            $case->fill([
                'stage'     => $data['stage'] ?? $case->stage,
                'status'    => $newStatus,
                'notes'     => $data['notes'] ?? $case->notes,
                'closed_at' => $closing ? ($case->closed_at ?? today()) : null,
            ]);

            // Passage en perte → écriture 6514/411 (idempotent : seulement si pas déjà passée)
            if ($newStatus === 'irrecouvrable' && !$case->journal_entry_id) {
                $case->save();
                $entry = $this->accountingService->postBadDebt($case->fresh());
                if ($entry) {
                    $case->journal_entry_id = $entry->id;
                }
            }

            $case->save();

            return $case->fresh(['client', 'invoice', 'journalEntry']);
        });
    }
}
