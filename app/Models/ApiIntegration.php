<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiIntegration extends Model
{
    protected $fillable = [
        'name', 'slug', 'type', 'provider',
        'base_url', 'sandbox_base_url', 'timeout_seconds',
        'api_key', 'secret_key', 'client_id', 'client_secret',
        'token', 'webhook_secret', 'extra_config',
        'mode', 'is_active', 'notify_on_error',
        'status', 'last_error', 'error_count', 'last_error_at',
        'last_success_at', 'last_tested_at',
    ];

    protected $casts = [
        'extra_config'    => 'array',
        'is_active'       => 'boolean',
        'notify_on_error' => 'boolean',
        'last_tested_at'  => 'datetime',
        'last_error_at'   => 'datetime',
        'last_success_at' => 'datetime',
        'timeout_seconds' => 'integer',
        'error_count'     => 'integer',
    ];

    // ── Encrypted sensitive fields ────────────────────────────────────────────

    public function setApiKeyAttribute(?string $v): void
    { $this->attributes['api_key'] = $v ? encrypt($v) : null; }
    public function getApiKeyAttribute(?string $v): ?string
    { if (!$v) return null; try { return decrypt($v); } catch (\Throwable) { return null; } }

    public function setSecretKeyAttribute(?string $v): void
    { $this->attributes['secret_key'] = $v ? encrypt($v) : null; }
    public function getSecretKeyAttribute(?string $v): ?string
    { if (!$v) return null; try { return decrypt($v); } catch (\Throwable) { return null; } }

    public function setClientIdAttribute(?string $v): void
    { $this->attributes['client_id'] = $v ? encrypt($v) : null; }
    public function getClientIdAttribute(?string $v): ?string
    { if (!$v) return null; try { return decrypt($v); } catch (\Throwable) { return null; } }

    public function setClientSecretAttribute(?string $v): void
    { $this->attributes['client_secret'] = $v ? encrypt($v) : null; }
    public function getClientSecretAttribute(?string $v): ?string
    { if (!$v) return null; try { return decrypt($v); } catch (\Throwable) { return null; } }

    public function setTokenAttribute(?string $v): void
    { $this->attributes['token'] = $v ? encrypt($v) : null; }
    public function getTokenAttribute(?string $v): ?string
    { if (!$v) return null; try { return decrypt($v); } catch (\Throwable) { return null; } }

    public function setWebhookSecretAttribute(?string $v): void
    { $this->attributes['webhook_secret'] = $v ? encrypt($v) : null; }
    public function getWebhookSecretAttribute(?string $v): ?string
    { if (!$v) return null; try { return decrypt($v); } catch (\Throwable) { return null; } }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function logs(): HasMany
    {
        return $this->hasMany(ApiLog::class);
    }

    public function externalTransactions(): HasMany
    {
        return $this->hasMany(ExternalTransaction::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeByType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type);
    }

    public function scopeInError(Builder $q): Builder
    {
        return $q->where('status', 'error');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Effective base URL depending on mode. */
    public function effectiveBaseUrl(): string
    {
        if ($this->mode === 'sandbox' && $this->sandbox_base_url) {
            return rtrim($this->sandbox_base_url, '/');
        }
        return rtrim($this->base_url ?? '', '/');
    }

    /** Webhook reception URL to communicate to the provider. */
    public function webhookUrl(): string
    {
        $slug = match($this->provider) {
            'orange_money' => 'orange-money',
            'moov_money'   => 'moov-money',
            default        => str_replace('_', '-', $this->provider),
        };
        return route('integrations.webhooks.' . $slug, [], true);
    }

    /** Whether a webhook URL is registered for this provider. */
    public function hasWebhook(): bool
    {
        return in_array($this->provider, ['orange_money', 'moov_money']);
    }

    public function isProduction(): bool
    {
        return $this->mode === 'production';
    }

    public function isSandbox(): bool
    {
        return $this->mode === 'sandbox';
    }

    public function isHealthy(): bool
    {
        return $this->status === 'ok' && $this->is_active;
    }

    public function typeLabel(): string
    {
        return match($this->type) {
            'payment'   => 'Paiement mobile',
            'sms'       => 'SMS Gateway',
            'email'     => 'Email SMTP/API',
            'bank'      => 'API Bancaire',
            'fiscal'    => 'API Fiscale',
            'ecommerce' => 'E-Commerce',
            default     => ucfirst($this->type),
        };
    }

    public function typeIcon(): string
    {
        return match($this->type) {
            'payment'   => '💳',
            'sms'       => '📱',
            'email'     => '📧',
            'bank'      => '🏦',
            'fiscal'    => '📋',
            'ecommerce' => '🛒',
            default     => '🔗',
        };
    }

    public function statusColor(): string
    {
        return match($this->status) {
            'ok'           => 'emerald',
            'error'        => 'red',
            'unconfigured' => 'gray',
            default        => 'amber',
        };
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'ok'           => 'Opérationnel',
            'error'        => 'Erreur',
            'unconfigured' => 'Non configuré',
            default        => 'Inconnu',
        };
    }

    /** Increment error counter and store the error message. */
    public function markError(string $message): void
    {
        $this->update([
            'status'       => 'error',
            'last_error'   => $message,
            'last_error_at' => now(),
            'error_count'  => ($this->error_count ?? 0) + 1,
        ]);
    }

    /** Mark as OK and reset error counter. */
    public function markOk(): void
    {
        $this->update([
            'status'          => 'ok',
            'last_error'      => null,
            'last_success_at' => now(),
            'last_tested_at'  => now(),
        ]);
    }
}
