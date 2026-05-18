<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialEffect extends Model
{
    use SoftDeletes, HasCreator, HasCompanyScope;

    protected $table = 'commercial_effects';

    protected $fillable = [
        'company_id', 'number', 'type', 'direction',
        'client_id', 'supplier_id', 'invoice_id', 'supplier_invoice_id',
        'amount', 'currency_code',
        'issue_date', 'due_date', 'acceptance_date', 'payment_date',
        'status',
        'drawer', 'drawee', 'payee',
        'bank_name', 'bank_account', 'reference',
        'bank_deposit_id', 'cash_account_id',
        'notes', 'rejection_reason', 'created_by',
    ];

    protected $casts = [
        'issue_date'      => 'date',
        'due_date'        => 'date',
        'acceptance_date' => 'date',
        'payment_date'    => 'date',
        'amount'          => 'integer',
    ];

    public function client(): BelongsTo    { return $this->belongsTo(Client::class); }
    public function supplier(): BelongsTo  { return $this->belongsTo(Supplier::class); }
    public function invoice(): BelongsTo   { return $this->belongsTo(Invoice::class); }
    public function supplierInvoice(): BelongsTo { return $this->belongsTo(SupplierInvoice::class); }
    public function bankDeposit(): BelongsTo { return $this->belongsTo(BankDeposit::class); }
    public function cashAccount(): BelongsTo { return $this->belongsTo(CashAccount::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function typeLabel(): string
    {
        return match($this->type) {
            'cheque'       => 'Chèque',
            'lcr'          => 'LCR',
            'billet_ordre' => 'Billet à ordre',
            'traite'       => 'Traite',
            default        => $this->type,
        };
    }

    public function directionLabel(): string
    {
        return match($this->direction) {
            'a_recevoir' => 'À recevoir',
            'a_payer'    => 'À payer',
            default      => $this->direction,
        };
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'en_attente'  => 'En attente',
            'accepte'     => 'Accepté',
            'remis_banque'=> 'Remis en banque',
            'encaisse'    => 'Encaissé',
            'rejete'      => 'Rejeté',
            'proteste'    => 'Protesté',
            'annule'      => 'Annulé',
            default       => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match($this->status) {
            'en_attente'  => 'yellow',
            'accepte'     => 'blue',
            'remis_banque'=> 'indigo',
            'encaisse'    => 'green',
            'rejete'      => 'red',
            'proteste'    => 'red',
            'annule'      => 'gray',
            default       => 'gray',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['en_attente', 'accepte']);
    }

    public function isDue(): bool
    {
        return $this->due_date && $this->due_date->isPast()
            && !in_array($this->status, ['encaisse', 'annule']);
    }
}
