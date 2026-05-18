<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal d'audit des modifications de séquences de numérotation.
 *
 * Chaque modification (format, compteur, reset, verrouillage) crée
 * une ligne immuable avec snapshots avant/après — traçabilité totale
 * pour conformité comptable et audit.
 */
class DocumentSequenceAudit extends Model
{
    public $timestamps = false;   // un seul champ : created_at, géré par DB

    protected $fillable = [
        'document_sequence_id',
        'user_id',
        'action',
        'before',
        'after',
        'reason',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'before'     => 'array',
        'after'      => 'array',
        'created_at' => 'datetime',
    ];

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(DocumentSequence::class, 'document_sequence_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Libellé lisible de l'action — pour l'UI.
     */
    public function getActionLabelAttribute(): string
    {
        return [
            'create'        => 'Création',
            'update_format' => 'Modification du format',
            'set_counter'   => 'Compteur défini manuellement',
            'reset_counter' => 'Remise à zéro',
            'lock'          => 'Verrouillage',
            'unlock'        => 'Déverrouillage',
            'next_number'   => 'Numéro généré',
        ][$this->action] ?? $this->action;
    }
}
