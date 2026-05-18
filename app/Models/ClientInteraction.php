<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientInteraction extends Model
{
    use HasFactory;

    protected $table = 'client_interactions';

    protected $fillable = [
        'client_id',
        'user_id',
        'type',
        'occurred_at',
        'subject',
        'notes',
        'outcome',
        'followup_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'followup_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
