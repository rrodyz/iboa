<?php

namespace App\Modules\Quality\Models;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** [QUALITÉ] Non-conformité + action corrective (CAPA). */
class NonConformity extends Model
{
    use HasFactory, HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id', 'quality_inspection_id', 'reference', 'title', 'description', 'severity',
        'status', 'corrective_action', 'responsible_id', 'due_date', 'closed_at', 'created_by',
    ];
    protected $casts = ['due_date' => 'date', 'closed_at' => 'date'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function inspection(): BelongsTo { return $this->belongsTo(QualityInspection::class, 'quality_inspection_id'); }
    public function responsible(): BelongsTo { return $this->belongsTo(Employee::class, 'responsible_id'); }

    public function severityLabel(): string { return match($this->severity){ 'mineure'=>'Mineure','majeure'=>'Majeure','critique'=>'Critique',default=>$this->severity }; }
    public function statusLabel(): string { return match($this->status){ 'ouverte'=>'Ouverte','en_cours'=>'En cours','cloturee'=>'Clôturée',default=>$this->status }; }

    protected static function newFactory() { return \Database\Factories\NonConformityFactory::new(); }
}
