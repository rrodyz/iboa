<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * [ACHATS-PRO-RFQ] Demande de devis (Request For Quotation).
 *
 * Workflow :
 *   brouillon → envoyee → recue → cloturee (PO généré) | annulee
 */
class Rfq extends Model
{
    use HasFactory, SoftDeletes, HasCompanyScope, HasCreator;

    protected $table = 'rfqs';

    protected $fillable = [
        'company_id', 'number', 'title', 'status', 'deadline', 'notes',
        'awarded_quote_id', 'purchase_order_id', 'created_by',
    ];

    protected $casts = ['deadline' => 'date'];

    public function items(): HasMany           { return $this->hasMany(RfqItem::class)->orderBy('sort_order'); }
    public function rfqSuppliers(): HasMany    { return $this->hasMany(RfqSupplier::class); }
    public function quotes(): HasMany          { return $this->hasMany(RfqQuote::class); }
    public function awardedQuote(): BelongsTo  { return $this->belongsTo(RfqQuote::class, 'awarded_quote_id'); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function createdBy(): BelongsTo     { return $this->belongsTo(User::class, 'created_by'); }

    public function isDraft(): bool       { return $this->status === 'brouillon'; }
    public function isSent(): bool        { return $this->status === 'envoyee'; }
    public function isReceived(): bool    { return $this->status === 'recue'; }
    public function isClosed(): bool      { return $this->status === 'cloturee'; }
    public function isCancelled(): bool   { return $this->status === 'annulee'; }
    public function isEditable(): bool    { return $this->isDraft(); }

    public function statusLabel(): string
    {
        return [
            'brouillon'=>'Brouillon','envoyee'=>'Envoyée','recue'=>'Réponses reçues',
            'cloturee'=>'Clôturée','annulee'=>'Annulée',
        ][$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return [
            'brouillon'=>'gray','envoyee'=>'blue','recue'=>'amber',
            'cloturee'=>'emerald','annulee'=>'red',
        ][$this->status] ?? 'gray';
    }
}
