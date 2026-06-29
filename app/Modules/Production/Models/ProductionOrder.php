<?php
namespace App\Modules\Production\Models;
use App\Models\Client;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** [PRODUCTION] Ordre de fabrication. */
class ProductionOrder extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id','fiscal_year_id','number','client_id','order_id','product_id','bill_of_material_id',
        'production_line_id','sheet_type','thickness','color','length','usable_width',
        'quantity_requested','quantity_produced','status','launched_at','finished_at','responsible_id','notes','created_by',
        // §13.2 CDC — Validation financière avant lancement OF
        'financial_authorization','financial_authorized_at','financial_authorized_by','financial_notes','payment_mode','payment_rate',
    ];
    protected $casts = [
        'thickness'=>'decimal:2','length'=>'decimal:2','usable_width'=>'decimal:1',
        'quantity_requested'=>'decimal:2','quantity_produced'=>'decimal:2','launched_at'=>'date','finished_at'=>'date',
        'financial_authorized_at'=>'datetime','payment_rate'=>'decimal:2',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function billOfMaterial(): BelongsTo { return $this->belongsTo(BillOfMaterial::class); }
    public function productionLine(): BelongsTo { return $this->belongsTo(ProductionLine::class); }
    public function responsible(): BelongsTo { return $this->belongsTo(User::class, 'responsible_id'); }
    public function lines(): HasMany { return $this->hasMany(ProductionOrderLine::class); }
    public function consumptions(): HasMany { return $this->hasMany(ProductionConsumption::class); }
    public function outputs(): HasMany { return $this->hasMany(ProductionOutput::class); }
    public function wastes(): HasMany { return $this->hasMany(ProductionWaste::class); }
    public function qualityControls(): HasMany { return $this->hasMany(ProductionQualityControl::class); }
    public function cost(): HasOne { return $this->hasOne(ProductionCost::class); }
    public function reservations(): HasMany { return $this->hasMany(\App\Models\StockReservation::class); }
    public function timeLogs(): HasMany { return $this->hasMany(ProductionTimeLog::class); }
    public function operations(): HasMany { return $this->hasMany(ProductionOrderOperation::class)->orderBy('sequence'); }
    public function batches(): HasMany { return $this->hasMany(ProductionBatch::class); }

    public function isEditable(): bool { return in_array($this->status, ['brouillon','matiere_allouee','lance'], true); }
    public function isInProgress(): bool { return in_array($this->status, ['en_cours','termine_partiellement'], true); }
    public function totalMeters(): float { return (float) $this->lines->sum('total_meters'); }
    public function statusLabel(): string { return match($this->status){'brouillon'=>'Brouillon','matiere_allouee'=>'Matière allouée','lance'=>'Lancé','en_cours'=>'En cours','termine_partiellement'=>'Terminé partiellement','termine'=>'Terminé','annule'=>'Annulé',default=>$this->status}; }
    public function statusColor(): string { return match($this->status){'brouillon'=>'gray','matiere_allouee'=>'amber','lance'=>'blue','en_cours'=>'sky','termine_partiellement'=>'teal','termine'=>'green','annule'=>'red',default=>'gray'}; }

    protected static function newFactory()
    {
        return \Database\Factories\ProductionOrderFactory::new();
    }
}
