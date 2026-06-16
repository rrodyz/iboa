<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * [TRESO] Promesse de paiement client (engagement de recouvrement, sans GL).
 */
class PaymentPromise extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id',
        'client_id',
        'invoice_id',
        'amount',
        'promised_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount'        => 'integer',
        'promised_date' => 'date',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** En attente et date dépassée → promesse à risque. */
    public function isOverdue(): bool
    {
        return $this->status === 'en_attente'
            && $this->promised_date
            && $this->promised_date->isPast();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'en_attente' => 'En attente',
            'tenue'      => 'Tenue',
            'non_tenue'  => 'Non tenue',
            'annulee'    => 'Annulée',
            default      => $this->status,
        };
    }
}
