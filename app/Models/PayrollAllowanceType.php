<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollAllowanceType extends Model
{
    protected $fillable = ['name', 'code', 'is_taxable', 'is_social_charged', 'description', 'is_active'];

    protected $casts = [
        'is_taxable'        => 'boolean',
        'is_social_charged' => 'boolean',
        'is_active'         => 'boolean',
    ];

    public function employeeAllowances(): HasMany { return $this->hasMany(EmployeeAllowance::class); }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
