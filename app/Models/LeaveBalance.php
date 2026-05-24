<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    protected $fillable = [
        'employee_id','leave_type_id','year','entitled_days','taken_days',
    ];

    protected $casts = [
        'year'          => 'integer',
        'entitled_days' => 'float',
        'taken_days'    => 'float',
    ];

    public function employee(): BelongsTo  { return $this->belongsTo(Employee::class); }
    public function leaveType(): BelongsTo { return $this->belongsTo(LeaveType::class); }

    public function getRemainingDaysAttribute(): float
    {
        return max(0, $this->entitled_days - $this->taken_days);
    }
}
