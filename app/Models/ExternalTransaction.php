<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalTransaction extends Model
{
    protected $fillable = [
        'internal_reference', 'external_reference', 'api_integration_id',
        'provider', 'type', 'amount', 'currency', 'status',
        'phone_number', 'provider_data',
        'invoice_id', 'client_id', 'client_payment_id',
        'direction', 'notes', 'transacted_at',
        'retry_count', 'last_retry_at', 'failure_reason', 'initiated_by',
    ];

    protected $casts = [
        'provider_data' => 'array',
        'amount'        => 'decimal:2',
        'transacted_at' => 'datetime',
        'last_retry_at' => 'datetime',
        'retry_count'   => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function integration(): BelongsTo
    {
        return $this->belongsTo(ApiIntegration::class, 'api_integration_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    public function scopeConfirmed(Builder $q): Builder
    {
        return $q->where('status', 'confirmed');
    }

    public function scopeFailed(Builder $q): Builder
    {
        return $q->where('status', 'failed');
    }

    public function scopeRetryable(Builder $q): Builder
    {
        return $q->where('status', 'failed')->where('retry_count', '<', 5);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function generateReference(): string
    {
        // EXT-<yyyymmdd>-<6 random hex chars>
        return 'EXT-' . now()->format('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
    }

    public function canRetry(): bool
    {
        return $this->status === 'failed' && ($this->retry_count ?? 0) < 5;
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'pending'   => 'En attente',
            'confirmed' => 'Confirmé',
            'failed'    => 'Échoué',
            'cancelled' => 'Annulé',
            default     => ucfirst((string) $this->status),
        };
    }

    public function statusColor(): string
    {
        return match($this->status) {
            'confirmed' => 'emerald',
            'pending'   => 'amber',
            'failed'    => 'red',
            'cancelled' => 'gray',
            default     => 'gray',
        };
    }

    public function typeLabel(): string
    {
        return match($this->type) {
            'payment'  => 'Paiement',
            'refund'   => 'Remboursement',
            'transfer' => 'Virement',
            default    => ucfirst((string) $this->type),
        };
    }
}
