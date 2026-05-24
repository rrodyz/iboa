<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * [RH-PRO] Document RH attaché à un employé.
 * CNIB, contrat, diplôme, certificat médical, etc.
 */
class EmployeeDocument extends Model
{
    protected $fillable = [
        'employee_id', 'document_type', 'label',
        'original_name', 'file_path', 'mime_type', 'file_size',
        'document_date', 'expires_at', 'notes', 'uploaded_by',
    ];

    protected $casts = [
        'document_date' => 'date',
        'expires_at'    => 'date',
        'file_size'     => 'integer',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function employee(): BelongsTo   { return $this->belongsTo(Employee::class); }
    public function uploadedBy(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function getDocumentTypeLabelAttribute(): string
    {
        return match ($this->document_type) {
            'cnib'        => 'CNIB',
            'passeport'   => 'Passeport',
            'contrat'     => 'Contrat de travail',
            'avenant'     => 'Avenant au contrat',
            'diplome'     => 'Diplôme / Certificat',
            'attestation' => 'Attestation',
            'medical'     => 'Certificat médical',
            'cnss'        => 'Carte CNSS',
            'photo'       => 'Photo',
            default       => 'Document',
        };
    }

    public function getDocumentTypeIconAttribute(): string
    {
        return match ($this->document_type) {
            'cnib', 'passeport' => '🪪',
            'contrat', 'avenant'=> '📄',
            'diplome'           => '🎓',
            'attestation'       => '📜',
            'medical'           => '🏥',
            'cnss'              => '🛡️',
            'photo'             => '📷',
            default             => '📎',
        };
    }

    public function getFileSizeHumanAttribute(): string
    {
        if (! $this->file_size) {
            return '—';
        }
        if ($this->file_size >= 1_048_576) {
            return round($this->file_size / 1_048_576, 1) . ' Mo';
        }
        if ($this->file_size >= 1_024) {
            return round($this->file_size / 1_024) . ' Ko';
        }
        return $this->file_size . ' o';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isExpiringSoon(): bool
    {
        return $this->expires_at
            && ! $this->isExpired()
            && $this->expires_at->diffInDays(now()) <= 30;
    }

    public function getDownloadUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }
}
