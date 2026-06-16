<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * [TRESO] Dossier de contentieux (recouvrement litigieux).
 */
class LitigationCase extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id',
        'client_id',
        'invoice_id',
        'number',
        'amount',
        'stage',
        'status',
        'opened_at',
        'closed_at',
        'notes',
        'journal_entry_id',
        'created_by',
    ];

    protected $casts = [
        'amount'    => 'integer',
        'opened_at' => 'date',
        'closed_at' => 'date',
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

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return in_array($this->status, ['ouvert', 'en_cours', 'suspendu'], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'ouvert'        => 'Ouvert',
            'en_cours'      => 'En cours',
            'suspendu'      => 'Suspendu',
            'recouvre'      => 'Recouvré',
            'irrecouvrable' => 'Irrécouvrable',
            default         => $this->status,
        };
    }

    public function stageLabel(): string
    {
        return match ($this->stage) {
            'mise_en_demeure' => 'Mise en demeure',
            'huissier'        => 'Huissier',
            'avocat'          => 'Avocat',
            'tribunal'        => 'Tribunal',
            'abandon'         => 'Abandon',
            default           => $this->stage,
        };
    }
}
