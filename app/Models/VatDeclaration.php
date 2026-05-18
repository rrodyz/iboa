<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VatDeclaration extends Model
{
    use SoftDeletes, HasCreator, HasCompanyScope;

    protected $table = 'vat_declarations';

    protected $fillable = [
        'company_id', 'number', 'period_label', 'period_type',
        'period_start', 'period_end', 'declaration_date', 'due_date',
        'status', 'tva_collectee', 'tva_deductible', 'tva_due',
        'credit_tva', 'amount_paid', 'notes', 'created_by',
    ];

    protected $casts = [
        'period_start'    => 'date',
        'period_end'      => 'date',
        'declaration_date'=> 'date',
        'due_date'        => 'date',
        'tva_collectee'   => 'integer',
        'tva_deductible'  => 'integer',
        'tva_due'         => 'integer',
        'credit_tva'      => 'integer',
        'amount_paid'     => 'integer',
    ];

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function isEditable(): bool { return $this->status === 'brouillon'; }

    public function statusLabel(): string
    {
        return match($this->status) {
            'brouillon' => 'Brouillon', 'soumis' => 'Soumis', 'paye' => 'Payé', default => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match($this->status) {
            'brouillon' => 'gray', 'soumis' => 'blue', 'paye' => 'green', default => 'gray',
        };
    }

    /** TVA restant à payer */
    public function getRemainingAttribute(): int
    {
        return max(0, $this->tva_due - $this->amount_paid);
    }
}
