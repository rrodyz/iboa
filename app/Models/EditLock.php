<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * [CONCURRENCE-MULTI-USER] Verrou pessimiste d'édition.
 *
 * @property string  $lockable_type
 * @property int     $lockable_id
 * @property int     $user_id
 * @property string  $session_id
 * @property \Carbon\Carbon $locked_at
 * @property \Carbon\Carbon $expires_at
 * @property-read User $user
 */
class EditLock extends Model
{
    protected $fillable = [
        'lockable_type',
        'lockable_id',
        'user_id',
        'session_id',
        'locked_at',
        'expires_at',
    ];

    protected $casts = [
        'locked_at'  => 'datetime',
        'expires_at' => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function lockable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return now()->gte($this->expires_at);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function isOwnedByCurrentSession(): bool
    {
        return $this->session_id === session()->getId();
    }

    /** Temps restant formaté (ex: "12 min 30 s") */
    public function remainingForHumans(): string
    {
        if ($this->isExpired()) {
            return 'expiré';
        }
        return now()->diffForHumans($this->expires_at, true);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>=', now());
    }
}
