<?php

namespace App\Repositories;

use App\Models\CashAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CashAccountRepository extends BaseRepository
{
    public function __construct(CashAccount $model)
    {
        parent::__construct($model);
    }

    /**
     * Return a cash account with its transactions paginated.
     */
    public function findWithTransactions(int $id, int $perPage = 20): CashAccount
    {
        $account = CashAccount::with(['paymentMethod'])->findOrFail($id);

        // Paginate transactions separately and attach as relation
        $account->setRelation(
            'transactions',
            $account->transactions()->paginate($perPage)
        );

        return $account;
    }

    /**
     * All active cash accounts for a company.
     */
    public function allActive(): \Illuminate\Database\Eloquent\Collection
    {
        return CashAccount::where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }
}
