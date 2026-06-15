<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\CashTransfer;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [TRESO] Virements internes entre comptes de trésorerie.
 * Débite le compte source, crédite le compte destination, génère l'écriture GL.
 * Tout dans une transaction — atomique. Soldes verrouillés (recordTransaction).
 */
class CashTransferService
{
    public function __construct(
        private DocumentSequenceService $seq,
        private CashAccountService $cashAccountService,
        private AccountingService $accountingService,
    ) {}

    /**
     * @throws \RuntimeException  comptes identiques / solde insuffisant
     */
    public function create(array $data): CashTransfer
    {
        return DB::transaction(function () use ($data) {
            $company = Company::findOrFail(Auth::user()->company_id);

            $fromId = (int) $data['from_cash_account_id'];
            $toId   = (int) $data['to_cash_account_id'];
            $amount = (int) $data['amount'];

            if ($fromId === $toId) {
                throw new \RuntimeException('Le compte source et le compte destination doivent être différents.');
            }
            if ($amount <= 0) {
                throw new \RuntimeException('Le montant du virement doit être positif.');
            }

            $from = CashAccount::findOrFail($fromId);
            $to   = CashAccount::findOrFail($toId);

            $transfer = CashTransfer::create([
                'company_id'           => $company->id,
                'from_cash_account_id' => $from->id,
                'to_cash_account_id'   => $to->id,
                'number'               => $this->seq->nextNumber($company, 'virement_interne'),
                'amount'               => $amount,
                'transfer_date'        => $data['transfer_date'] ?? today(),
                'reference'            => $data['reference'] ?? null,
                'notes'                => $data['notes'] ?? null,
                'status'               => 'valide',
                'created_by'           => Auth::id(),
            ]);

            // Débit source (recordTransaction refuse un solde négatif)
            $this->cashAccountService->recordTransaction($from, [
                'type'             => 'debit',
                'amount'           => $amount,
                'reference_type'   => 'cash_transfer',
                'reference_id'     => $transfer->id,
                'label'            => 'Virement ' . $transfer->number . ' → ' . $to->name,
                'transaction_date' => $transfer->transfer_date,
            ]);

            // Crédit destination
            $this->cashAccountService->recordTransaction($to, [
                'type'             => 'credit',
                'amount'           => $amount,
                'reference_type'   => 'cash_transfer',
                'reference_id'     => $transfer->id,
                'label'            => 'Virement ' . $transfer->number . ' ← ' . $from->name,
                'transaction_date' => $transfer->transfer_date,
            ]);

            // Écriture comptable (DR destination / CR source)
            $this->accountingService->postCashTransfer($transfer->fresh(['fromAccount', 'toAccount']));

            return $transfer->fresh(['fromAccount', 'toAccount', 'journalEntry']);
        });
    }

    /**
     * Annule un virement : recrédite la source, redébite la destination,
     * contre-passe l'écriture comptable. Échoue si la destination n'a plus
     * les fonds (déjà dépensés).
     *
     * @throws \RuntimeException
     */
    public function cancel(CashTransfer $transfer, string $motif): void
    {
        if (trim($motif) === '') {
            throw new \RuntimeException("Le motif d'annulation est obligatoire.");
        }

        DB::transaction(function () use ($transfer, $motif) {
            // [CONCURRENCE] Verrou + re-check statut frais.
            $transfer = CashTransfer::lockForUpdate()->findOrFail($transfer->id);
            if (! $transfer->isCancellable()) {
                throw new \RuntimeException("Ce virement a déjà été annulé.");
            }

            $from   = CashAccount::findOrFail($transfer->from_cash_account_id);
            $to     = CashAccount::findOrFail($transfer->to_cash_account_id);
            $amount = (int) $transfer->amount;

            // Redébiter la destination (refuse si solde insuffisant = fonds déjà utilisés)
            $this->cashAccountService->recordTransaction($to, [
                'type'             => 'debit',
                'amount'           => $amount,
                'reference_type'   => 'cash_transfer_cancel',
                'reference_id'     => $transfer->id,
                'label'            => 'Annulation virement ' . $transfer->number,
                'transaction_date' => today(),
            ]);

            // Recréditer la source
            $this->cashAccountService->recordTransaction($from, [
                'type'             => 'credit',
                'amount'           => $amount,
                'reference_type'   => 'cash_transfer_cancel',
                'reference_id'     => $transfer->id,
                'label'            => 'Annulation virement ' . $transfer->number,
                'transaction_date' => today(),
            ]);

            // Contre-passation comptable
            if ($transfer->journalEntry) {
                $this->accountingService->reverseEntry(
                    $transfer->journalEntry,
                    'Annulation virement ' . $transfer->number . ' — ' . $motif
                );
            }

            $transfer->update([
                'status' => 'annule',
                'notes'  => trim(($transfer->notes ? $transfer->notes . "\n" : '') . 'Annulé : ' . $motif),
            ]);
        });
    }
}
