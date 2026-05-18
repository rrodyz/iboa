<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RfqSupplier extends Model
{
    protected $table = 'rfq_suppliers';
    protected $fillable = ['rfq_id', 'supplier_id', 'status', 'sent_at', 'response_received_at', 'notes'];
    protected $casts = ['sent_at' => 'datetime', 'response_received_at' => 'datetime'];

    public function rfq(): BelongsTo      { return $this->belongsTo(Rfq::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function quote(): HasOne       { return $this->hasOne(RfqQuote::class); }
}
