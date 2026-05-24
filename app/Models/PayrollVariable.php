<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Variable mensuelle de paie (heures supp, absences, primes ponctuelles, avances).
 */
class PayrollVariable extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id',
        'type', 'label', 'qty', 'unit', 'unit_amount', 'amount',
        'is_gain', 'is_taxable', 'is_social_charged', 'note',
    ];

    protected $casts = [
        'qty'               => 'float',
        'unit_amount'       => 'float',
        'amount'            => 'integer',
        'is_gain'           => 'boolean',
        'is_taxable'        => 'boolean',
        'is_social_charged' => 'boolean',
    ];

    // ─── Types disponibles ─────────────────────────────────────────────────────
    const TYPES = [
        'hs_25'               => ['label' => 'Heures supp. 25%',     'gain' => true,  'taxable' => true,  'unit' => 'heures'],
        'hs_50'               => ['label' => 'Heures supp. 50%',     'gain' => true,  'taxable' => true,  'unit' => 'heures'],
        'hs_nuit'             => ['label' => 'Heures de nuit',        'gain' => true,  'taxable' => true,  'unit' => 'heures'],
        'prime_exceptionnelle'=> ['label' => 'Prime exceptionnelle',  'gain' => true,  'taxable' => true,  'unit' => 'forfait'],
        'indemnite_cp'        => ['label' => 'Indemnité congés payés','gain' => true,  'taxable' => false, 'unit' => 'forfait'],
        'gain_autre'          => ['label' => 'Autre gain',            'gain' => true,  'taxable' => true,  'unit' => 'forfait'],
        'absence_injust'      => ['label' => 'Absence injustifiée',   'gain' => false, 'taxable' => false, 'unit' => 'jours'],
        'absence_maladie'     => ['label' => 'Absence maladie',       'gain' => false, 'taxable' => false, 'unit' => 'jours'],
        'absence_cp'          => ['label' => 'Congé payé',            'gain' => false, 'taxable' => false, 'unit' => 'jours'],
        'avance_deduction'    => ['label' => 'Récupération avance',   'gain' => false, 'taxable' => false, 'unit' => 'forfait'],
        'retenue_autre'       => ['label' => 'Autre retenue',         'gain' => false, 'taxable' => false, 'unit' => 'forfait'],
    ];

    public function payrollRun(): BelongsTo { return $this->belongsTo(PayrollRun::class); }
    public function employee(): BelongsTo   { return $this->belongsTo(Employee::class); }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type]['label'] ?? $this->type;
    }

    public function getSignedAmountAttribute(): int
    {
        return $this->is_gain ? $this->amount : -$this->amount;
    }
}
