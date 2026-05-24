<?php

namespace App\Observers\Concerns;

use App\Models\AuditLog;
use App\Services\AuditService;

/**
 * [TRACE] Trait mutualisé pour les observers métier.
 *
 * Trace dans `audit_logs` les événements created/updated/deleted/restored
 * en filtrant le bruit (timestamps, totaux recalculés, etc.) et en ne
 * conservant que les transitions d'état métier (status, approval, etc.).
 *
 * Utilisation :
 *   class QuoteObserver { use TracksLifecycle; ... }
 *
 * Override possible : SUMMARY_FIELDS, IGNORED_FIELDS, modelLabel().
 */
trait TracksLifecycle
{
    /** Champs ignorés lors du diff updated (timestamps, recalculés…). */
    protected array $ignoredFields = [
        'updated_at',
        'created_at',
        'subtotal_ht', 'total_tax', 'total_ttc', 'total_discount',
        'withholding_details', 'withholding_amount', 'net_to_pay',
        'remaining_amount', 'paid_amount', 'allocated_amount', 'unallocated_amount',
        'total_debit', 'total_credit',
    ];

    /**
     * Champs résumés au moment du created/deleted (pour avoir le contexte).
     * Override dans l'observer enfant via cette méthode (les propriétés ne sont pas
     * surchargeables si leur valeur diffère du trait).
     */
    protected function summaryFields(): array
    {
        return ['number', 'status', 'reference'];
    }

    protected function auditService(): AuditService
    {
        return app(AuditService::class);
    }

    /**
     * Renvoie le résumé d'un modèle pour les events created/deleted.
     */
    protected function summary($model): array
    {
        $arr = [];
        foreach ($this->summaryFields() as $field) {
            if (isset($model->$field)) {
                $val = $model->$field;
                if ($val instanceof \DateTimeInterface) $val = $val->toDateString();
                $arr[$field] = $val;
            }
        }
        return $arr;
    }

    /**
     * Renvoie la diff utile des champs modifiés (filtrée).
     */
    protected function meaningfulDiff($model): array
    {
        $changes = [];
        foreach ($model->getDirty() as $key => $newValue) {
            if (in_array($key, $this->ignoredFields, true)) continue;
            $oldValue = $model->getOriginal($key);
            $changes[$key] = ['old' => $oldValue, 'new' => $newValue];
        }
        return $changes;
    }

    public function created($model): void
    {
        $this->auditService()->log('created', $model, [], $this->summary($model));
    }

    public function updated($model): void
    {
        $diff = $this->meaningfulDiff($model);
        if (empty($diff)) return;   // skip noise updates

        $old = []; $new = [];
        foreach ($diff as $field => $pair) {
            $old[$field] = $pair['old'];
            $new[$field] = $pair['new'];
        }

        // [TRACE] Action enrichie : si seul "status" change, on log "status_changed"
        $action = 'updated';
        if (array_keys($new) === ['status']) {
            $action = 'status_changed';
        } elseif (isset($new['approval_status']) && count($new) === 1) {
            $action = 'approval_' . $new['approval_status'];
        }

        $this->auditService()->log($action, $model, $old, $new);
    }

    public function deleted($model): void
    {
        $this->auditService()->log('deleted', $model, $this->summary($model), []);
    }

    public function restored($model): void
    {
        $this->auditService()->log('restored', $model, [], $this->summary($model));
    }
}
