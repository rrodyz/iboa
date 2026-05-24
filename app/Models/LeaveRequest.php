<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    protected $fillable = [
        'employee_id','leave_type_id','start_date','end_date','days',
        'reason','status','approved_by','approved_at','notes','created_by',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'days'        => 'float',
        'approved_at' => 'datetime',
    ];

    const STATUSES = [
        'en_attente' => ['label' => 'En attente', 'color' => 'yellow'],
        'approuve'   => ['label' => 'Approuvé',   'color' => 'green'],
        'refuse'     => ['label' => 'Refusé',     'color' => 'red'],
        'annule'     => ['label' => 'Annulé',     'color' => 'gray'],
    ];

    public function employee(): BelongsTo   { return $this->belongsTo(Employee::class); }
    public function leaveType(): BelongsTo  { return $this->belongsTo(LeaveType::class); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function createdBy(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status]['label'] ?? $this->status;
    }
    public function getStatusColorAttribute(): string
    {
        return self::STATUSES[$this->status]['color'] ?? 'gray';
    }
}
