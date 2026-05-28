<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiLog extends Model
{
    public $timestamps = false; // only created_at

    protected $fillable = [
        'api_integration_id', 'service', 'endpoint', 'method',
        'payload', 'response', 'status_code', 'success',
        'duration_ms', 'reference', 'direction', 'error_message',
        'ip_address', 'user_agent', 'job_id', 'created_at',
    ];

    protected $casts = [
        'payload'     => 'array',
        'response'    => 'array',
        'success'     => 'boolean',
        'duration_ms' => 'float',
        'created_at'  => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function integration(): BelongsTo
    {
        return $this->belongsTo(ApiIntegration::class, 'api_integration_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeSuccessful(Builder $q): Builder { return $q->where('success', true); }
    public function scopeFailed(Builder $q): Builder     { return $q->where('success', false); }
    public function scopeInbound(Builder $q): Builder    { return $q->where('direction', 'inbound'); }
    public function scopeOutbound(Builder $q): Builder   { return $q->where('direction', 'outbound'); }
    public function scopeToday(Builder $q): Builder      { return $q->whereDate('created_at', today()); }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function durationLabel(): string
    {
        if ($this->duration_ms === null) return '—';
        return $this->duration_ms >= 1000
            ? round($this->duration_ms / 1000, 1) . 's'
            : round($this->duration_ms) . 'ms';
    }

    public function methodColor(): string
    {
        return match(strtoupper((string) $this->method)) {
            'GET'          => 'blue',
            'POST'         => 'emerald',
            'PUT', 'PATCH' => 'amber',
            'DELETE'       => 'red',
            'WEBHOOK'      => 'violet',
            default        => 'gray',
        };
    }
}
