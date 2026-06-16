<?php

namespace App\Models\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Adds a `creator()` relationship alias for models that store `created_by`.
 *
 * Usage: use HasCreator; (requires a `createdBy()` method on the model)
 *
 * Allows eager-loading via ->with('creator:id,name') and
 * template access via $model->creator?->name.
 */
trait HasCreator
{
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Alias — plusieurs vues/contrôleurs trésorerie référencent `createdBy`. */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
