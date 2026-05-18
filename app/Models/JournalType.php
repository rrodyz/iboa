<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalType extends Model
{
    use HasCompanyScope;

    protected $table = 'journal_types';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function typeLabel(): string
    {
        return match($this->type) {
            'achat'               => 'Achats',
            'vente'               => 'Ventes',
            'banque'              => 'Banque',
            'caisse'              => 'Caisse',
            'operations_diverses' => 'Opérations diverses',
            'a_nouveau'           => 'À nouveau',
            default               => $this->type,
        };
    }
}
