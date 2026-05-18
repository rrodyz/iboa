<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashFlowForecast extends Model
{
    use SoftDeletes, HasCreator, HasCompanyScope;

    protected $table = 'cash_flow_forecasts';

    protected $fillable = [
        'company_id', 'number', 'label', 'period_type',
        'period_start', 'period_end',
        'opening_balance', 'total_inflows', 'total_outflows',
        'net_flow', 'closing_balance_forecast',
        'actual_inflows', 'actual_outflows',
        'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'period_start'             => 'date',
        'period_end'               => 'date',
        'opening_balance'          => 'integer',
        'total_inflows'            => 'integer',
        'total_outflows'           => 'integer',
        'net_flow'                 => 'integer',
        'closing_balance_forecast' => 'integer',
        'actual_inflows'           => 'integer',
        'actual_outflows'          => 'integer',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function lines(): HasMany
    {
        return $this->hasMany(CashFlowForecastLine::class, 'forecast_id')->orderBy('sort_order');
    }

    public function inflows(): HasMany
    {
        return $this->hasMany(CashFlowForecastLine::class, 'forecast_id')
                    ->where('is_inflow', true)->orderBy('sort_order');
    }

    public function outflows(): HasMany
    {
        return $this->hasMany(CashFlowForecastLine::class, 'forecast_id')
                    ->where('is_inflow', false)->orderBy('sort_order');
    }

    public function isEditable(): bool { return $this->status === 'brouillon'; }

    public function statusLabel(): string
    {
        return match($this->status) {
            'brouillon' => 'Brouillon', 'valide' => 'Validé', default => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match($this->status) {
            'brouillon' => 'gray', 'valide' => 'green', default => 'gray',
        };
    }

    /** Variance between actual and forecast net flow */
    public function getVarianceAttribute(): int
    {
        $actualNet = $this->actual_inflows - $this->actual_outflows;
        return $actualNet - $this->net_flow;
    }
}
