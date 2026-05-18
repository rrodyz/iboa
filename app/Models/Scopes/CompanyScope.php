<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * GlobalScope that automatically restricts all queries to the current company.
 *
 * Uses a correlated subquery instead of a hard-coded ID so that:
 *  - No extra DB round-trip is needed on every model boot.
 *  - Works in CLI commands (no authenticated user required).
 *  - MySQL evaluates the subquery once per outer query (constant folding).
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where(
            $model->getTable() . '.company_id',
            fn ($q) => $q->select('id')->from('companies')->orderBy('id')->limit(1)
        );
    }
}
