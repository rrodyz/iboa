<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [ACHATS-PRO-APPROVAL] Tranche d'approbation par montant.
 *
 * Convention :
 *   - min_amount inclusif, max_amount exclusif (ou NULL = pas de plafond)
 *   - On retient la première règle active dont la tranche couvre le montant
 */
class PoApprovalThreshold extends Model
{
    use HasCompanyScope;

    protected $table = 'po_approval_thresholds';

    protected $fillable = [
        'company_id', 'name', 'min_amount', 'max_amount',
        'required_role', 'required_permission', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'is_active'  => 'boolean',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    /**
     * Trouve la règle d'approbation qui couvre $amount pour $companyId.
     * Renvoie null si aucune règle ne s'applique (= aucune approbation requise).
     */
    public static function findForAmount(int $companyId, float $amount): ?self
    {
        return static::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('min_amount', '<=', $amount)
            ->where(function ($q) use ($amount) {
                $q->whereNull('max_amount')->orWhere('max_amount', '>', $amount);
            })
            ->orderBy('sort_order')
            ->orderByDesc('min_amount')
            ->first();
    }

    /**
     * L'utilisateur a-t-il les droits requis par cette règle ?
     */
    public function canBeApprovedBy(User $user): bool
    {
        if ($user->hasRole('super_admin')) return true;
        if ($this->required_role && $user->hasRole($this->required_role)) return true;
        if ($this->required_permission && $user->can($this->required_permission)) return true;
        // Si aucune contrainte définie, défaut = permission générique
        if (!$this->required_role && !$this->required_permission) {
            return $user->can('purchase_orders.validate');
        }
        return false;
    }
}
