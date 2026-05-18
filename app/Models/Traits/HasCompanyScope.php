<?php

namespace App\Models\Traits;

use App\Models\Scopes\CompanyScope;

/**
 * Automatically restricts all Eloquent queries on the model to the company
 * stored in the `companies` table (single-company ERP).
 *
 * Usage: add `use HasCompanyScope;` to any model that has a `company_id` column.
 *
 * The scope uses Laravel's `bootHasCompanyScope()` convention so it fires
 * automatically without needing a `booted()` override in the model.
 */
trait HasCompanyScope
{
    protected static function bootHasCompanyScope(): void
    {
        static::addGlobalScope(new CompanyScope());
    }
}
