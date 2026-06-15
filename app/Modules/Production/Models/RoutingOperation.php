<?php

namespace App\Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** [PRODUCTION] Opération d'une gamme (poste + temps). */
class RoutingOperation extends Model
{
    use HasFactory;

    protected $fillable = ['routing_id', 'work_center_id', 'sequence', 'name', 'setup_minutes', 'run_minutes_per_unit'];
    protected $casts = ['setup_minutes' => 'decimal:2', 'run_minutes_per_unit' => 'decimal:2'];

    public function routing(): BelongsTo { return $this->belongsTo(Routing::class); }
    public function workCenter(): BelongsTo { return $this->belongsTo(WorkCenter::class); }

    protected static function newFactory() { return \Database\Factories\RoutingOperationFactory::new(); }
}
