<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** [TRESO] Ligne de budget : catégorie × direction × mois → montant prévu. */
class TreasuryBudgetLine extends Model
{
    protected $fillable = ['treasury_budget_id','category','direction','month','planned_amount'];
    protected $casts = ['month' => 'integer', 'planned_amount' => 'integer'];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(TreasuryBudget::class, 'treasury_budget_id');
    }
}
