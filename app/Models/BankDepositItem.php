<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankDepositItem extends Model
{
    protected $table = 'bank_deposit_items';

    protected $fillable = [
        'bank_deposit_id', 'type', 'amount', 'reference',
        'drawer', 'bank_name', 'due_date',
        'commercial_effect_id', 'notes', 'sort_order',
    ];

    protected $casts = [
        'amount'   => 'integer',
        'due_date' => 'date',
    ];

    public function bankDeposit(): BelongsTo
    {
        return $this->belongsTo(BankDeposit::class);
    }

    public function commercialEffect(): BelongsTo
    {
        return $this->belongsTo(CommercialEffect::class);
    }

    public function typeLabel(): string
    {
        return match($this->type) {
            'especes'  => 'Espèces',
            'cheque'   => 'Chèque',
            'effet'    => 'Effet',
            'virement' => 'Virement',
            default    => $this->type,
        };
    }
}
