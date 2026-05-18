<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FiscalYear extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fiscal_years';

    protected $fillable = [
        'label',
        'starts_at',
        'ends_at',
        'status',
        'is_current',
    ];

    protected $casts = [
        'starts_at'  => 'date',
        'ends_at'    => 'date',
        'is_current' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Scope: only the current fiscal year.
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope: only open fiscal years.
     */
    public function scopeOuvert(Builder $query): Builder
    {
        return $query->where('status', 'ouvert');
    }
}
