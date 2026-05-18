<?php

namespace App\Services;

use App\Events\PaymentReceived;
use App\Models\CashAccount;
use App\Models\ClientPayment;
use App\Models\Invoice;
use App\Repositories\ClientPaymentRepository;
use App\Services\AccountingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClientPaymentService
{
    public function __construct(
        public readonly ClientPaymentRepository $repository,
        protected DocumentSequenceService $sequenceService,
        protected CashAccountService $cashService,
        protected AccountingService $accountingService,
    ) {}

    public function create(array $data): ClientPayment
    {
        return DB::transaction(function () use ($data) {
            $allocations = $data['allocations'] ?? [];
            unset($data['allocations']);

            $data['created_by'] = Auth::id();

            // Generate payment number
            $company = Auth::user()->company;
            if ($company) {
                $data['company_id'] = $company->id;
                $data['number'] = $this->sequenceService->nextNumber($company, 'encaissement');
            } else {
                $data['number'] = 'ENC-' . date('YmdHis');
            }

            // Calculate unallocated amount initially = full amount
            $data['unallocated_amount'] = $data['amount'];
            $data['allocated_amount']   = 0;

            $payment = $this->repository->create($data);

            $totalAllocated = 0;

            // [FIX-CRITIQUE] Pre-validate: total allocations must not exceed payment amount
            $requestedTotal = collect($allocations)->sum(fn($a) => (int) ($a['allocated_amount'] ?? 0));
            if ($requestedTotal > (int) $data['amount']) {
                throw new \RuntimeException(
                    'Le total des allocations (' . number_format($requestedTotal, 0, ',', ' ') . ' FCFA) '
                    . 'dépasse le montant du paiement (' . number_format($data['amount'], 0, ',', ' ') . ' FCFA).'
                );
            }

            foreach ($allocations as $alloc) {
                if (empty($alloc['invoice_id']) || empty($alloc['allocated_amount'])) {
                    continue;
                }
                $amount = (int) $alloc['allocated_amount'];
                if ($amount <= 0) {
                    continue;
                }

                // [FIX-CRITIQUE] Lock invoice row to prevent concurrent double-allocation
                $invoice = Invoice::lockForUpdate()->find($alloc['invoice_id']);
                if (!$invoice) {
                    continue;
                }

                // [SÉCURITÉ] La facture doit appartenir au même client que le paiement
                if ((int) $invoice->client_id !== (int) $payment->client_id) {
                    throw new \RuntimeException(
                        'La facture ' . $invoice->number . ' n\'appartient pas au client sélectionné.'
                    );
                }

                // [FIX-MAJEUR] Cap allocation to actual remaining amount
                $amount = min($amount, (int) $invoice->remaining_amount);
                if ($amount <= 0) {
                    continue;
                }

                $payment->allocations()->create([
                    'client_payment_id' => $payment->id,
                    'invoice_id'        => $invoice->id,
                    'amount'            => $amount,
                    'allocated_at'      => now(),
                    'created_by'        => Auth::id(),
                ]);

                $totalAllocated += $amount;

                // Update invoice paid/remaining/status
                // [FIX-WITHHOLDING-PAY] Compute remaining against NET_TO_PAY (= total_ttc - withholding),
                // not raw total_ttc — otherwise an invoice with retenue à la source can never reach
                // "payée" because the client only pays the net portion (the State collects the withholding).
                $newPaid       = $invoice->paid_amount + $amount;
                $netToPay      = (int) ($invoice->net_to_pay ?? max(0, $invoice->total_ttc - ($invoice->withholding_amount ?? 0)));
                $newRemaining  = max(0, $netToPay - $newPaid);
                $invoice->update([
                    'paid_amount'      => $newPaid,
                    'remaining_amount' => $newRemaining,
                    'status'           => $newRemaining <= 0 ? 'payee' : 'partiellement_payee',
                ]);
            }

            // Update allocated/unallocated on the payment
            $payment->update([
                'allocated_amount'   => $totalAllocated,
                'unallocated_amount' => max(0, $payment->amount - $totalAllocated),
            ]);

            // Enregistrer la transaction de caisse si un compte est lié
            if (!empty($data['cash_account_id'])) {
                $cashAccount = CashAccount::find($data['cash_account_id']);
                if ($cashAccount) {
                    $this->cashService->recordTransaction($cashAccount, [
                        'type'             => 'credit',
                        'reference_type'   => 'ClientPayment',
                        'reference_id'     => $payment->id,
                        'amount'           => $payment->amount,
                        'label'            => 'Encaissement '.$payment->number.' — '.$payment->client?->displayName(),
                        'transaction_date' => $payment->payment_date ?? today(),
                    ]);
                }
            }

            // Post to GL synchronously — must be in the same transaction
            $this->accountingService->postClientPayment($payment->fresh(['client', 'company']));

            // Fire event — listener recalculates client balance after commit
            event(new PaymentReceived($payment));

            return $payment;
        });
    }

    /**
     * Return unpaid (validated or partial) invoices for a given client.
     */
    public function getClientUnpaidInvoices(int $clientId): Collection
    {
        return Invoice::where('client_id', $clientId)
            ->whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])
            ->where('remaining_amount', '>', 0)
            ->orderBy('due_at')
            ->get(['id', 'number', 'issued_at', 'due_at', 'total_ttc', 'remaining_amount', 'status']);
    }
}
