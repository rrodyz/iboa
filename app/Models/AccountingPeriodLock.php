<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [COMPTA-PRO-05] Représente un verrouillage mensuel d'écritures.
 *
 * Quand une ligne existe pour (company_id, year, month), toutes les écritures
 * dont entry_date tombe dans ce mois sont gelées : validation, modification,
 * suppression et contre-passation interdites.
 */
class AccountingPeriodLock extends Model
{
    use HasCompanyScope;

    protected $table = 'accounting_period_locks';

    protected $fillable = [
        'company_id', 'year', 'month', 'locked_at', 'locked_by', 'reason',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
        'year'      => 'integer',
        'month'     => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * Renvoie le lock pour un mois donné, ou null si non verrouillé.
     */
    public static function findFor(int $companyId, int $year, int $month): ?self
    {
        return static::where('company_id', $companyId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }

    /**
     * Renvoie le lock dans lequel tombe une date donnée, ou null.
     */
    public static function findForDate(int $companyId, \DateTimeInterface $date): ?self
    {
        return static::findFor($companyId, (int) $date->format('Y'), (int) $date->format('n'));
    }

    public function label(): string
    {
        $months = [
            1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',
            7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre',
        ];
        return ($months[$this->month] ?? $this->month) . ' ' . $this->year;
    }
}
