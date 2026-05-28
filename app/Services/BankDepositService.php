<?php

namespace App\Services;

use App\Models\BankDeposit;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BankDepositService
{
    public function __construct(
        private DocumentSequenceService $seq,
        private CashAccountService $cashAccountService,
        private AccountingService $accountingService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Create
    // ─────────────────────────────────────────────────────────────────────────
    public function create(array $data): BankDeposit
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $company            = Company::findOrFail(Auth::user()->company_id);
            $data['company_id'] = $company->id;
            $data['number']     = $this->seq->nextNumber($company, 'remise_banque');
            $data['created_by'] = Auth::id();
            $data['status']     = 'brouillon';

            $deposit = BankDeposit::create($data);

            foreach ($items as $i => $item) {
                if (empty($item['amount']) || (int) $item['amount'] <= 0) continue;
                $deposit->items()->create([
                    'type'                => $item['type'] ?? 'especes',
                    'amount'              => (int) $item['amount'],
                    'reference'           => $item['reference'] ?? null,
                    'drawer'              => $item['drawer'] ?? null,
                    'bank_name'           => $item['bank_name'] ?? null,
                    'due_date'            => $item['due_date'] ?? null,
                    'commercial_effect_id'=> $item['commercial_effect_id'] ?? null,
                    'notes'               => $item['notes'] ?? null,
                    'sort_order'          => $i,
                ]);
            }

            $this->recalculate($deposit);
            return $deposit->fresh();
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Validate — update balances + GL entry + mark effects as remis_banque
    // ─────────────────────────────────────────────────────────────────────────
    public function validateDeposit(BankDeposit $deposit): BankDeposit
    {
        if ($deposit->status !== 'brouillon') {
            throw new \RuntimeException('Cette remise est déjà validée.');
        }

        return DB::transaction(function () use ($deposit) {
            $deposit->load(['items', 'cashAccount', 'sourceCashAccount']);

            // Credit bank account (trésorerie)
            $this->cashAccountService->recordTransaction($deposit->cashAccount, [
                'type'             => 'credit',
                'amount'           => $deposit->total_amount,
                'label'            => 'Remise en banque ' . $deposit->number,
                'transaction_date' => $deposit->deposit_date->toDateString(),
                'reference_type'   => BankDeposit::class,
                'reference_id'     => $deposit->id,
                'created_by'       => Auth::id(),
            ]);

            // Debit source caisse if set
            if ($deposit->sourceCashAccount) {
                $this->cashAccountService->recordTransaction($deposit->sourceCashAccount, [
                    'type'             => 'debit',
                    'amount'           => $deposit->total_amount,
                    'label'            => 'Remise banque ' . $deposit->number . ' — versement',
                    'transaction_date' => $deposit->deposit_date->toDateString(),
                    'reference_type'   => BankDeposit::class,
                    'reference_id'     => $deposit->id,
                    'created_by'       => Auth::id(),
                ]);
            }

            // Update linked commercial effects to remis_banque
            $effectIds = $deposit->items()
                ->whereNotNull('commercial_effect_id')
                ->pluck('commercial_effect_id');

            if ($effectIds->isNotEmpty()) {
                \App\Models\CommercialEffect::whereIn('id', $effectIds)
                    ->update([
                        'status'          => 'remis_banque',
                        'bank_deposit_id' => $deposit->id,
                        'cash_account_id' => $deposit->cash_account_id,
                    ]);
            }

            $deposit->update([
                'status'       => 'valide',
                'validated_by' => Auth::id(),
                'validated_at' => now(),
            ]);

            // [COMPTA-BANQUE] Post GL entry — DR 521 Banque / CR 571 Caisse (ou 585 transit)
            // Must be called after status update so the fresh deposit is 'valide'
            $this->accountingService->postBankDeposit($deposit->fresh(['cashAccount', 'sourceCashAccount']));

            return $deposit->fresh();
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    private function recalculate(BankDeposit $deposit): void
    {
        $deposit->load('items');
        $total = (int) $deposit->items->sum('amount');
        $deposit->update(['total_amount' => $total]);
    }
}
