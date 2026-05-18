<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'disk',
        'path',
        'filename',
        'mime_type',
        'size',
        'label',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // -------------------------------------------------------------------------
    // Computed
    // -------------------------------------------------------------------------

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function humanSize(): string
    {
        $bytes = $this->size;
        if ($bytes < 1024) return $bytes.' o';
        if ($bytes < 1048576) return round($bytes / 1024, 1).' Ko';
        return round($bytes / 1048576, 1).' Mo';
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function iconClass(): string
    {
        if ($this->isImage()) return 'text-green-500';
        if ($this->isPdf())   return 'text-red-500';
        return 'text-gray-400';
    }
}
