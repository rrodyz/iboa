<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Audit trail immuable des transitions de statut des documents commerciaux.
 *
 * @property int         $id
 * @property string      $document_type
 * @property int         $document_id
 * @property string|null $ancien_statut
 * @property string      $nouveau_statut
 * @property string      $action
 * @property int         $user_id
 * @property string|null $user_role
 * @property string|null $motif
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array|null  $metadata
 * @property \Carbon\Carbon $created_at
 */
class CommercialValidation extends Model
{
    // Immuable : pas d'updated_at
    const UPDATED_AT = null;

    protected $table = 'commercial_validations';

    protected $fillable = [
        'document_type',
        'document_id',
        'ancien_statut',
        'nouveau_statut',
        'action',
        'user_id',
        'user_role',
        'motif',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    // ── Actions disponibles ───────────────────────────────────────────────────
    const ACTION_SOUMISSION    = 'soumission';
    const ACTION_VALIDATION    = 'validation';
    const ACTION_REFUS         = 'refus';
    const ACTION_ANNULATION    = 'annulation';
    const ACTION_TRANSFORMATION = 'transformation';
    const ACTION_CREATION      = 'creation';

    // Labels lisibles
    public static array $actionLabels = [
        'creation'       => 'Création',
        'soumission'     => 'Soumis à validation',
        'validation'     => 'Validé',
        'refus'          => 'Refusé',
        'annulation'     => 'Annulé',
        'transformation' => 'Transformé',
    ];

    public static array $actionColors = [
        'creation'       => 'gray',
        'soumission'     => 'yellow',
        'validation'     => 'green',
        'refus'          => 'red',
        'annulation'     => 'gray',
        'transformation' => 'blue',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getActionLabelAttribute(): string
    {
        return self::$actionLabels[$this->action] ?? ucfirst($this->action);
    }

    public function getActionColorAttribute(): string
    {
        return self::$actionColors[$this->action] ?? 'gray';
    }
}
