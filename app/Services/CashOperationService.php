<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\CashOperation;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [TRESO] Opérations diverses de caisse : entrées (apport, recette diverse)
 * et sorties (dépense diverse, petty cash). Met à jour le solde + écriture GL.
 */
class CashOperationService
{
    public function __construct(
        private DocumentSequenceService $seq,
        private CashAccountService $cashAccountService,
        private AccountingService $accountingService,
    ) {}

    /**
     * @throws \RuntimeException montant invalide / solde insuffisant
     */
    public function create(array $data): CashOperation
    {
        return DB::transaction(function () use ($data) {
            $company = Company::findOrFail(Auth::user()->company_id);
            $account = CashAccount::findOrFail((int) $data['cash_account_id']);
            $amount  = (int) $data['amount'];

            if ($amount <= 0) {
                throw new \RuntimeException('Le montant de l\'opération doit être positif.');
            }

            $direction = $data['direction'] === 'entree' ? 'entree' : 'sortie';

            $operation = CashOperation::create([
                'company_id'      => $company->id,
                'cash_account_id' => $account->id,
                'number'          => $this->seq->nextNumber($company, 'operation_caisse'),
                'direction'       => $direction,
                'category'        => $data['category'] ?? null,
                'amount'          => $amount,
                'operation_date'  => $data['operation_date'] ?? today(),
                'label'           => $data['label'] ?? null,
                'status'          => 'valide',
                'created_by'      => Auth::id(),
            ]);

            // Mouvement de trésorerie (credit = entrée, debit = sortie ; refuse solde négatif)
            $this->cashAccountService->recordTransaction($account, [
                'type'             => $direction === 'entree' ? 'credit' : 'debit',
                'amount'           => $amount,
                'reference_type'   => 'cash_operation',
                'reference_id'     => $operation->id,
                'label'            => $operation->label ?: ($operation->category ?: $operation->directionLabel() . ' de caisse'),
                'transaction_date' => $operation->operation_date,
            ]);

            // Écriture comptable
            $entry = $this->accountingService->postCashOperation($operation);
            if ($entry) {
                $operation->update(['journal_entry_id' => $entry->id]);
            }

            return $operation->fresh(['cashAccount', 'journalEntry']);
        });
    }

    /**
     * Annule une opération : inverse le mouvement de trésorerie + contre-passe le GL.
     *
     * @throws \RuntimeException
     */
    public function cancel(CashOperation $operation, string $motif): void
    {
        if (trim($motif) === '') {
            throw new \RuntimeException("Le motif d'annulation est obligatoire.");
        }

        DB::transaction(function () use ($operation, $motif) {
            $operation = CashOperation::lockForUpdate()->findOrFail($operation->id);
            if (! $operation->isCancellable()) {
                throw new \RuntimeException('Cette opération a déjà été annulée.');
            }

            $account = CashAccount::findOrFail($operation->cash_account_id);
            $amount  = (int) $operation->amount;

            // Inverse : une entrée se débite, une sortie se recrédite (refuse solde négatif)
            $this->cashAccountService->recordTransaction($account, [
                'type'             => $operation->direction === 'entree' ? 'debit' : 'credit',
                'amount'           => $amount,
                'reference_type'   => 'cash_operation_cancel',
                'reference_id'     => $operation->id,
                'label'            => 'Annulation ' . $operation->number,
                'transaction_date' => today(),
            ]);

            if ($operation->journalEntry) {
                $this->accountingService->reverseEntry(
                    $operation->journalEntry,
                    'Annulation ' . $operation->number . ' — ' . $motif
                );
            }

            $operation->update([
                'status' => 'annule',
                'label'  => trim(($operation->label ? $operation->label . ' | ' : '') . 'Annulé : ' . $motif),
            ]);
        });
    }
}
