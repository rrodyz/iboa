<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Compteur de séquence par période pour une règle de numérotation.
 * Ne jamais modifier last_seq directement — passer par BulletinNumberingService.
 */
class PayrollNumberingSequence extends Model
{
    protected $fillable = ['numbering_id', 'period_key', 'last_seq'];

    protected $casts = ['last_seq' => 'integer'];

    public function numbering(): BelongsTo
    {
        return $this->belongsTo(PayrollNumbering::class, 'numbering_id');
    }
}
