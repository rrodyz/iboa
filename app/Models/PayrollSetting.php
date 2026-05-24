<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * [RH-PRO] Paramétrage de la paie.
 * Singleton par company_id — un seul enregistrement par entreprise.
 */
class PayrollSetting extends Model
{
    protected $fillable = [
        'company_id',
        'cnss_employee_rate', 'cnss_employer_rate', 'cnss_ceiling', 'cnss_at_rate',
        'work_days_month', 'work_hours_day',
        'hs_rate_25', 'hs_rate_50', 'hs_rate_nuit',
        'nb_parts_max', 'parts_per_child',
        'iuts_brackets',
        'bulletin_prefix', 'currency_code', 'country_code',
        'notes', 'updated_by',
    ];

    protected $casts = [
        'cnss_employee_rate' => 'float',
        'cnss_employer_rate' => 'float',
        'cnss_ceiling'       => 'integer',
        'cnss_at_rate'       => 'float',
        'work_days_month'    => 'integer',
        'work_hours_day'     => 'integer',
        'hs_rate_25'         => 'float',
        'hs_rate_50'         => 'float',
        'hs_rate_nuit'       => 'float',
        'nb_parts_max'       => 'integer',
        'parts_per_child'    => 'float',
        'iuts_brackets'      => 'array',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function updatedBy(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Récupère ou crée les paramètres de paie pour une entreprise.
     * Utilise le cache pour éviter des requêtes répétées.
     */
    public static function forCompany(int $companyId): self
    {
        return Cache::remember("payroll_settings_{$companyId}", 600, function () use ($companyId) {
            return self::firstOrCreate(
                ['company_id' => $companyId],
                ['iuts_brackets' => self::defaultIutsBrackets()]
            );
        });
    }

    /**
     * Invalide le cache après modification.
     */
    public static function clearCache(int $companyId): void
    {
        Cache::forget("payroll_settings_{$companyId}");
    }

    /**
     * Barème IUTS par défaut — Burkina Faso 2024.
     * Format : [[plafond_tranche, taux_pct], ...]
     * La dernière tranche a un plafond très élevé (∞).
     */
    public static function defaultIutsBrackets(): array
    {
        return [
            [25_000,          0],
            [40_000,         12],
            [60_000,         17],
            [80_000,         22],
            [120_000,        27],
            [9_999_999_999,  33],
        ];
    }

    /**
     * Calcule l'IUTS par la méthode du quotient familial.
     */
    public function computeIuts(int $imposable, float $parts): int
    {
        if ($imposable <= 0 || $parts <= 0) {
            return 0;
        }

        $brackets = $this->iuts_brackets ?: self::defaultIutsBrackets();
        $quotient = $imposable / $parts;
        $tax      = 0.0;
        $prev     = 0;

        foreach ($brackets as [$limit, $rate]) {
            if ($quotient <= $prev) {
                break;
            }
            $tranche = min($quotient, $limit) - $prev;
            $tax    += $tranche * $rate / 100;
            $prev    = $limit;
            if ($quotient <= $limit) {
                break;
            }
        }

        return (int) round($tax * $parts);
    }

    /**
     * Calcule le nombre de parts fiscales selon situation familiale.
     */
    public function computeNbParts(string $familyStatus, int $nbChildren): float
    {
        $parts = match ($familyStatus) {
            'marie'  => 2.0,
            'veuf'   => 1.5,
            default  => 1.0,
        };
        $parts += $nbChildren * $this->parts_per_child;
        return min($parts, (float) $this->nb_parts_max);
    }
}
