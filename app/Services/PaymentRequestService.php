<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PaymentRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [TRESO] Demandes de paiement : brouillon → soumis → validé → payé.
 * La validation respecte les seuils (TreasuryApprovalService). Le paiement
 * convertit la demande validée en décaissement (SupplierPaymentService).
 */
class PaymentRequestService
{
    public function __construct(
        private DocumentSequenceService $seq,
        private TreasuryApprovalService $approvalService,
        private SupplierPaymentService $paymentService,
    ) {}

    public function create(array $data): PaymentRequest
    {
        return DB::transaction(function () use ($data) {
            $company = Company::findOrFail(Auth::user()->company_id);
            $data['company_id']   = $company->id;
            $data['number']       = $this->seq->nextNumber($company, 'demande_paiement');
            $data['status']       = 'brouillon';
            $data['requested_by'] = Auth::id();

            return PaymentRequest::create($data);
        });
    }

    public function update(PaymentRequest $request, array $data): PaymentRequest
    {
        if (! $request->isEditable()) {
            throw new \RuntimeException('Seule une demande en brouillon peut être modifiée.');
        }
        $request->update($data);
        return $request->fresh();
    }

    public function submit(PaymentRequest $request): PaymentRequest
    {
        if (! $request->isSubmittable()) {
            throw new \RuntimeException('Cette demande ne peut pas être soumise.');
        }
        $rule = $this->approvalService->findRequiredRule((int) $request->company_id, (int) $request->amount);
        $request->update([
            'status'        => 'soumis',
            'required_role' => $rule?->required_role,
            'submitted_at'  => now(),
        ]);
        return $request->fresh();
    }

    public function approve(PaymentRequest $request): PaymentRequest
    {
        return DB::transaction(function () use ($request) {
            $request = PaymentRequest::lockForUpdate()->findOrFail($request->id);
            if (! $request->isValidatable()) {
                throw new \RuntimeException('Cette demande n\'est pas en attente de validation.');
            }
            $rule = $this->approvalService->findRequiredRule((int) $request->company_id, (int) $request->amount);
            if (! $this->approvalService->userCanApprove(Auth::user(), $rule)) {
                throw new \RuntimeException(
                    "Niveau insuffisant pour valider cette demande"
                    . ($rule?->required_role ? " (rôle « {$rule->required_role} »)." : '.')
                );
            }
            $request->update([
                'status'       => 'valide',
                'validated_by' => Auth::id(),
                'validated_at' => now(),
            ]);
            return $request->fresh();
        });
    }

    public function reject(PaymentRequest $request, string $motif): PaymentRequest
    {
        $motif = trim($motif);
        if ($motif === '') {
            throw new \RuntimeException('Le motif de rejet est obligatoire.');
        }
        return DB::transaction(function () use ($request, $motif) {
            $request = PaymentRequest::lockForUpdate()->findOrFail($request->id);
            if (! $request->isRejectable()) {
                throw new \RuntimeException('Cette demande n\'est pas en attente de validation.');
            }
            $request->update([
                'status'           => 'rejete',
                'rejected_by'      => Auth::id(),
                'rejected_at'      => now(),
                'rejection_reason' => $motif,
            ]);
            return $request->fresh();
        });
    }

    /**
     * Convertit une demande validée en décaissement.
     * @throws \RuntimeException
     */
    public function pay(PaymentRequest $request, array $paymentData): PaymentRequest
    {
        return DB::transaction(function () use ($request, $paymentData) {
            $request = PaymentRequest::lockForUpdate()->findOrFail($request->id);
            if (! $request->isPayable()) {
                throw new \RuntimeException('Seule une demande validée peut être payée.');
            }

            $allocations = [];
            if ($request->supplier_invoice_id) {
                $allocations[] = [
                    'supplier_invoice_id' => $request->supplier_invoice_id,
                    'allocated_amount'    => (int) $request->amount,
                ];
            }

            $payment = $this->paymentService->create([
                'company_id'        => $request->company_id,
                'supplier_id'       => $request->supplier_id,
                'cash_account_id'   => $paymentData['cash_account_id'] ?? null,
                'payment_method_id' => $paymentData['payment_method_id'] ?? $request->payment_method_id,
                'amount'            => (int) $request->amount,
                'payment_date'      => $paymentData['payment_date'] ?? today(),
                'reference'         => $request->number,
                'notes'             => 'Demande de paiement ' . $request->number,
                'allocations'       => $allocations,
                '_pre_authorized'   => true, // déjà validé au niveau de la demande
            ]);

            $request->update([
                'status'              => 'paye',
                'supplier_payment_id' => $payment->id,
            ]);

            return $request->fresh(['supplierPayment']);
        });
    }
}
