<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

/**
 * [TRESO] Seuil d'approbation des décaissements : bande de montant → rôle requis.
 */
class TreasuryApprovalThreshold extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id',
        'name',
        'min_amount',
        'max_amount',
        'required_role',
        'required_permission',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'min_amount' => 'integer',
        'max_amount' => 'integer',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];
}
