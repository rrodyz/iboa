<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\CashClosure;
use App\Models\CashTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CashAccountService
{
    /**
     * Return the computed balance for a cash account
     * (opening_balance + sum of credits - sum of debits).
     */
    public function getBalance(CashAccount $account): int
    {
        $credits = (int) $account->transactions()->where('type', 'credit')->sum('amount');
        $debits  = (int) $account->transactions()->where('type', 'debit')->sum('amount');

        return $account->opening_balance + $credits - $debits;
    }

    /**
     * Record a manual transaction against the given cash account,
     * updating current_balance on the account.
     */
    public function recordTransaction(CashAccount $account, array $data): CashTransaction
    {
        return DB::transaction(function () use ($account, $data) {
            // [ARCH-C1] Lock the account row to prevent concurrent balance corruption.
            $account = CashAccount::lockForUpdate()->find($account->id);

            // Compute balance after this transaction
            $amount       = (int) $data['amount'];
            $balanceAfter = $account->current_balance
                + ($data['type'] === 'credit' ? $amount : -$amount);

            // [TRESO-P7] Reject back-dated movements into a validated closure period.
            // Skip the closure's own écart adjustment (reference_type=cash_closure).
            if (($data['reference_type'] ?? null) !== 'cash_closure') {
                $txDate = $data['transaction_date'] ?? today();
                $lockedClosure = CashClosure::where('cash_account_id', $account->id)
                    ->where('status', 'valide')
                    ->whereDate('closure_date', '>=', $txDate)
                    ->orderByDesc('closure_date')
                    ->first();
                if ($lockedClosure) {
                    throw new \RuntimeException('Caisse clôturée au '.\Illuminate\Support\Carbon::parse($lockedClosure->closure_date)->format('d/m/Y').' : impossible d\'enregistrer un mouvement à cette date ou antérieure.');
                }
            }

            // [FIX-TRESO-01] Reject transactions that would make the balance negative
            if ($balanceAfter < 0) {
                throw new \RuntimeException('Solde insuffisant : le solde du compte ne peut pas être négatif (solde actuel : '.number_format($account->current_balance, 0, ',', ' ').' FCFA).');
            }

            $transaction = CashTransaction::create([
                'cash_account_id'  => $account->id,
                'type'             => $data['type'],
                'reference_type'   => $data['reference_type'] ?? null,
                'reference_id'     => $data['reference_id'] ?? null,
                'amount'           => $amount,
                'balance_after'    => $balanceAfter,
                'label'            => $data['label'] ?? null,
                'transaction_date' => $data['transaction_date'] ?? today(),
                'created_by'       => Auth::id(),
            ]);

            // Keep current_balance in sync on the account row
            $account->update(['current_balance' => $balanceAfter]);

            return $transaction;
        });
    }
}
