<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContact extends Model
{
    use HasFactory;

    protected $table = 'client_contacts';

    protected $fillable = [
        'client_id',
        'civility',
        'first_name',
        'last_name',
        'job_title',
        'department',
        'phone',
        'mobile',
        'email',
        'is_primary',
        'receives_invoices',
    ];

    protected $casts = [
        'is_primary'        => 'boolean',
        'receives_invoices' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    // -------------------------------------------------------------------------
    // Methods
    // -------------------------------------------------------------------------

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
