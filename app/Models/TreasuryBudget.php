<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** [TRESO] Budget de trésorerie (en-tête). */
class TreasuryBudget extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope;

    protected $fillable = ['company_id','fiscal_year_id','name','year','status','notes','created_by'];
    protected $casts = ['year' => 'integer'];

    public function lines(): HasMany
    {
        return $this->hasMany(TreasuryBudgetLine::class);
    }
}
