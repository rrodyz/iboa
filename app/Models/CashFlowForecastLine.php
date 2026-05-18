<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashFlowForecastLine extends Model
{
    protected $table = 'cash_flow_forecast_lines';

    protected $fillable = [
        'forecast_id', 'category', 'label', 'is_inflow',
        'forecast_amount', 'actual_amount', 'sort_order',
    ];

    protected $casts = [
        'is_inflow'       => 'boolean',
        'forecast_amount' => 'integer',
        'actual_amount'   => 'integer',
    ];

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(CashFlowForecast::class, 'forecast_id');
    }

    public function getVarianceAttribute(): int
    {
        return $this->actual_amount - $this->forecast_amount;
    }

    public static function categoryLabel(string $cat): string
    {
        return match($cat) {
            'encaissements_clients'  => 'Encaissements clients',
            'ventes_comptant'        => 'Ventes au comptant',
            'autres_encaissements'   => 'Autres encaissements',
            'achats_fournisseurs'    => 'Achats fournisseurs',
            'salaires'               => 'Salaires & charges sociales',
            'charges_fiscales'       => 'Impôts & taxes',
            'investissements'        => 'Investissements',
            'remboursements_emprunts'=> 'Remboursements d\'emprunts',
            'autres_charges'         => 'Autres charges',
            default                  => ucfirst(str_replace('_', ' ', $cat)),
        };
    }

    public static function inflowCategories(): array
    {
        return ['encaissements_clients', 'ventes_comptant', 'autres_encaissements'];
    }

    public static function outflowCategories(): array
    {
        return ['achats_fournisseurs', 'salaires', 'charges_fiscales', 'investissements', 'remboursements_emprunts', 'autres_charges'];
    }
}
