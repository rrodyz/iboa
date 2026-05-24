<?php

namespace App\Services;

use App\Events\SupplierPaymentCreated;
use App\Models\CashAccount;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\SupplierInvoice;
use App\Repositories\SupplierPaymentRepository;
use App\Services\AccountingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupplierPaymentService
{
    public function __construct(
        public readonly SupplierPaymentRepository $repository,
        protected DocumentSequenceService $sequenceService,
        protected CashAccountService $cashService,
        protected AccountingService $accountingService,
    ) {}

    public function create(array $data): SupplierPayment
    {
        return DB::transaction(function () use ($data) {
            $allocations = $data['allocations'] ?? [];
            unset($data['allocations']);

            $data['created_by'] = Auth::id();

            // [DOUBLE-PAYMENT-GUARD] Anti-doublon décaissement (cf. ClientPaymentService).
            $this->assertNoDuplicateRecent($data);

            // Generate payment number
            $company = Auth::user()->company;
            if ($company) {
                $data['company_id'] = $company->id;
                $data['number'] = $this->sequenceService->nextNumber($company, 'decaissement');
            } else {
                $data['number'] = 'DEC-' . date('YmdHis');
            }

            // Calculate unallocated amount initially = full amount
            $data['unallocated_amount'] = $data['amount'];
            $data['allocated_amount']   = 0;

            $payment = $this->repository->create($data);

            $totalAllocated = 0;

            // Pre-validate: total allocations must not exceed payment amount
            $requestedTotal = collect($allocations)->sum(fn($a) => (int) ($a['allocated_amount'] ?? 0));
            if ($requestedTotal > (int) $data['amount']) {
                throw new \RuntimeException(
                    'Le total des allocations (' . number_format($requestedTotal, 0, ',', ' ') . ' FCFA) '
                    . 'dépasse le montant du paiement (' . number_format($data['amount'], 0, ',', ' ') . ' FCFA).'
                );
            }

            foreach ($allocations as $alloc) {
                if (empty($alloc['supplier_invoice_id']) || empty($alloc['allocated_amount'])) {
                    continue;
                }
                $amount = (int) $alloc['allocated_amount'];
                if ($amount <= 0) {
                    continue;
                }

                // Lock invoice row to prevent concurrent double-allocation
                $invoice = SupplierInvoice::lockForUpdate()->find($alloc['supplier_invoice_id']);
                if (!$invoice) {
                    continue;
                }

                // [SÉCURITÉ] La facture doit appartenir au même fournisseur que le paiement
                if ((int) $invoice->supplier_id !== (int) $payment->supplier_id) {
                    throw new \RuntimeException(
                        'La facture ' . $invoice->number . ' n\'appartient pas au fournisseur sélectionné.'
                    );
                }

                // [INVOICE-LOCKED-GUARD] Refus explicite d'allouer un décaissement à une FF
                // entièrement payée (status=payee) ou annulée.
                if (in_array($invoice->status, ['payee', 'annulee'], true)) {
                    throw new \RuntimeException(sprintf(
                        "La facture fournisseur %s est %s — aucune nouvelle allocation n'est autorisée.",
                        $invoice->number, $invoice->status === 'payee' ? 'déjà entièrement payée' : 'annulée'
                    ));
                }
                if ((int) $invoice->remaining_amount <= 0) {
                    throw new \RuntimeException(sprintf(
                        "La facture fournisseur %s a un reste à payer nul — elle est techniquement soldée.",
                        $invoice->number
                    ));
                }

                // Cap allocation to actual remaining amount
                $amount = min($amount, (int) $invoice->remaining_amount);
                if ($amount <= 0) {
                    continue;
                }

                $payment->allocations()->create([
                    'supplier_payment_id' => $payment->id,
                    'supplier_invoice_id' => $invoice->id,
                    'amount'              => $amount,
                    'allocated_at'        => now(),
                    'created_by'          => Auth::id(),
                ]);

                $totalAllocated += $amount;

                // Update supplier invoice paid/remaining/status
                $newPaid      = $invoice->paid_amount + $amount;
                $newRemaining = max(0, $invoice->total_ttc - $newPaid);
                $invoice->update([
                    'paid_amount'      => $newPaid,
                    'remaining_amount' => $newRemaining,
                    'status'           => $newRemaining <= 0 ? 'payee' : 'partiellement_payee',
                ]);

                // [ACHATS-PRO-SCHEDULE] Si un cadencier existe, impute le paiement
                // en ordre chronologique sur les échéances en attente.
                if ($invoice->paymentSchedules()->exists()) {
                    app(\App\Services\PaymentScheduleService::class)->applyPayment($invoice, (float) $amount);
                }
            }

            // Update allocated/unallocated on the payment
            $payment->update([
                'allocated_amount'   => $totalAllocated,
                'unallocated_amount' => max(0, $payment->amount - $totalAllocated),
            ]);

            // Post to GL synchronously — must be in the same transaction
            $this->accountingService->postSupplierPayment($payment->fresh(['supplier', 'company']));

            // Enregistrer la transaction de caisse si un compte est lié
            if (!empty($data['cash_account_id'])) {
                $cashAccount = CashAccount::find($data['cash_account_id']);
                if ($cashAccount) {
                    $this->cashService->recordTransaction($cashAccount, [
                        'type'             => 'debit',
                        'reference_type'   => 'SupplierPayment',
                        'reference_id'     => $payment->id,
                        'amount'           => $payment->amount,
                        'label'            => 'Décaissement '.$payment->number.' — '.$payment->supplier?->name,
                        'transaction_date' => $payment->payment_date ?? today(),
                    ]);
                }
            }

            // Fire event — queued listeners update supplier balance + log after commit
            event(new SupplierPaymentCreated($payment));

            return $payment;
        });
    }

    /**
     * Annule un décaissement fournisseur déjà confirmé.
     *
     * Procédure SAFE :
     *   1. Lock du payment + check statut (refuse si déjà 'annule')
     *   2. Pour chaque allocation : remettre la facture parent en état non-payée
     *   3. Contre-passation comptable (via AccountingService::reverseEntry)
     *   4. Restitution du solde caisse si une cash_transaction existait
     *   5. Marquage du payment en 'annule' (preserve historique, pas de hard delete)
     *
     * @param  string|null  $reason  Motif obligatoire pour audit comptable
     * @throws \RuntimeException si déjà annulé ou état invalide
     */
    public function cancel(SupplierPayment $payment, ?string $reason = null): SupplierPayment
    {
        $reason = trim((string) $reason);
        if ($reason === '') {
            throw new \RuntimeException('Motif d\'annulation obligatoire (traçabilité comptable).');
        }

        return DB::transaction(function () use ($payment, $reason) {
            // Lock du payment pour éviter double annulation
            $payment = SupplierPayment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === 'annule') {
                throw new \RuntimeException('Ce décaissement est déjà annulé.');
            }

            $payment->load('allocations', 'cashAccount');

            // 1. Restaurer chaque facture allouée
            foreach ($payment->allocations as $alloc) {
                $invoice = \App\Models\SupplierInvoice::lockForUpdate()->find($alloc->supplier_invoice_id);
                if (!$invoice) continue;

                $newPaid      = max(0, (int) $invoice->paid_amount - (int) $alloc->amount);
                $newRemaining = max(0, (int) $invoice->total_ttc - $newPaid);

                $newStatus = $invoice->status;
                if ($newRemaining === (int) $invoice->total_ttc) {
                    $newStatus = 'validee';  // entièrement restaurée
                } elseif ($newRemaining > 0) {
                    $newStatus = 'partiellement_payee';
                }

                $invoice->update([
                    'paid_amount'      => $newPaid,
                    'remaining_amount' => $newRemaining,
                    'status'           => $newStatus,
                ]);
            }

            // Supprimer les allocations (cascade pas garantie)
            $payment->allocations()->delete();

            // 2. Contre-passation de l'écriture comptable
            $entry = \App\Models\JournalEntry::where('reference', $payment->number)
                ->where('company_id', $payment->company_id)
                ->where('status', 'valide')
                ->first();
            if ($entry) {
                $this->accountingService->reverseEntry(
                    $entry,
                    'Annulation décaissement ' . $payment->number . ' — ' . $reason
                );
            }

            // 3. Restitution caisse si une cash_transaction existait
            //    (Si elle n'existait pas — cas des paiements historiques pré-sync — on saute.)
            $cashTx = \App\Models\CashTransaction::where('reference_type', 'App\\Models\\SupplierPayment')
                ->where('reference_id', $payment->id)
                ->orWhere(function ($q) use ($payment) {
                    $q->where('reference_type', 'SupplierPayment')->where('reference_id', $payment->id);
                })
                ->first();
            if ($cashTx && $payment->cashAccount) {
                // On crée un mouvement inverse (credit pour restituer ce qu'on avait débité)
                $this->cashService->recordTransaction($payment->cashAccount, [
                    'type'             => 'credit',
                    'reference_type'   => 'SupplierPayment',
                    'reference_id'     => $payment->id,
                    'amount'           => $payment->amount,
                    'label'            => 'Annulation décaissement ' . $payment->number,
                    'transaction_date' => today(),
                ]);
            }

            // 4. Marquer le payment annulé + trace motif
            $cancelNote = sprintf(
                "[ANNULATION %s par %s] %s",
                now()->format('d/m/Y H:i'),
                Auth::user()?->name ?? 'système',
                $reason
            );
            $payment->update([
                'status' => 'annule',
                'notes'  => trim(($payment->notes ?? '') . "\n\n" . $cancelNote),
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Return unpaid supplier invoices for a given supplier.
     */
    public function getSupplierUnpaidInvoices(int $supplierId): Collection
    {
        return SupplierInvoice::where('supplier_id', $supplierId)
            ->whereIn('status', ['validee', 'partiellement_payee'])
            ->where('remaining_amount', '>', 0)
            ->orderBy('due_at')
            ->get(['id', 'number', 'supplier_invoice_number', 'received_at', 'due_at', 'total_ttc', 'remaining_amount', 'status']);
    }

    /**
     * [DOUBLE-PAYMENT-GUARD] Lève une exception si un décaissement strictement
     * identique a été créé dans les 60 dernières secondes (double-clic, soumission
     * concurrente, replay réseau), OU si la référence saisie est déjà utilisée
     * pour ce fournisseur.
     */
    private function assertNoDuplicateRecent(array $data): void
    {
        $supplierId      = (int) ($data['supplier_id'] ?? 0);
        $amount          = (int) ($data['amount']      ?? 0);
        $paymentMethodId = $data['payment_method_id']  ?? null;
        $cashAccountId   = $data['cash_account_id']    ?? null;
        $reference       = trim((string) ($data['reference'] ?? ''));
        $forceDuplicate  = (bool) ($data['force_duplicate'] ?? false);

        if ($supplierId <= 0 || $amount <= 0) return;

        $exists = SupplierPayment::where('supplier_id', $supplierId)
            ->where('amount', $amount)
            ->where('payment_method_id', $paymentMethodId)
            ->where('cash_account_id', $cashAccountId)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subSeconds(60))
            ->first();

        if ($exists) {
            throw new \RuntimeException(sprintf(
                "Un décaissement identique vient d'être enregistré il y a quelques secondes : %s (%s FCFA). "
                . "Attendez 1 minute ou modifiez un attribut (méthode, référence…) pour confirmer un second décaissement légitime.",
                $exists->number,
                number_format($exists->amount, 0, ',', ' ')
            ));
        }

        // [DUP-24H-GUARD] Doublon métier sur 24h pour même fournisseur + même montant
        if (!$forceDuplicate) {
            $sameAmountToday = SupplierPayment::where('supplier_id', $supplierId)
                ->where('amount', $amount)
                ->whereNull('deleted_at')
                ->where('created_at', '>=', now()->subHours(24))
                ->count();
            if ($sameAmountToday > 0) {
                $unpaidSum = (int) SupplierInvoice::where('supplier_id', $supplierId)
                    ->whereIn('status', ['validee','partiellement_payee','en_retard'])
                    ->whereNull('deleted_at')->sum('remaining_amount');
                throw new \RuntimeException(sprintf(
                    "⚠ Doublon probable : ce fournisseur a déjà reçu %d décaissement(s) de %s FCFA dans les 24h. "
                    . "Total restant à payer au fournisseur : %s FCFA. "
                    . "Pour confirmer ce décaissement supplémentaire, cochez « Forcer le paiement (doublon confirmé) ».",
                    $sameAmountToday,
                    number_format($amount, 0, ',', ' '),
                    number_format($unpaidSum, 0, ',', ' ')
                ));
            }
        }

        // [OVERPAYMENT-GUARD] Refus si le fournisseur a déjà assez de décaissements
        // non imputés pour couvrir tout son passif → empêche les saisies répétées.
        if (!$forceDuplicate) {
            $unallocatedSum = (int) SupplierPayment::where('supplier_id', $supplierId)
                ->whereNull('deleted_at')->sum('unallocated_amount');
            $outstandingSum = (int) SupplierInvoice::where('supplier_id', $supplierId)
                ->whereIn('status', ['validee','partiellement_payee','en_retard'])
                ->whereNull('deleted_at')->sum('remaining_amount');

            if ($unallocatedSum >= $outstandingSum && $outstandingSum >= 0) {
                throw new \RuntimeException(sprintf(
                    "⚠ Doublon structurel : %s FCFA de décaissements non imputés sont déjà disponibles "
                    . "pour ce fournisseur alors qu'il ne reste que %s FCFA à régler. "
                    . "Imputez d'abord les décaissements existants sur ses factures avant d'en saisir un nouveau. "
                    . "Si c'est un véritable nouveau paiement (avance), cochez « Forcer le paiement ».",
                    number_format($unallocatedSum, 0, ',', ' '),
                    number_format($outstandingSum, 0, ',', ' ')
                ));
            }
        }

        if ($reference !== '') {
            $dup = SupplierPayment::where('supplier_id', $supplierId)
                ->where('reference', $reference)
                ->whereNull('deleted_at')
                ->first();
            if ($dup) {
                throw new \RuntimeException(sprintf(
                    "La référence « %s » est déjà utilisée pour ce fournisseur (décaissement %s). "
                    . "Vérifiez qu'il ne s'agit pas d'un doublon — ou ajoutez un suffixe à la référence.",
                    $reference, $dup->number
                ));
            }
        }
    }
}
