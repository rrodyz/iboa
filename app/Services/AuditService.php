<?php
namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public function log(
        string $action,
        ?object $model = null,
        array $oldValues = [],
        array $newValues = []
    ): void {
        AuditLog::create([
            'user_id'     => Auth::id(),
            'user_name'   => Auth::user()?->name ?? 'Système',
            'action'      => $action,
            'model_type'  => $model ? get_class($model) : null,
            'model_id'    => $model?->getKey(),
            'old_values'  => $oldValues ?: null,
            'new_values'  => $newValues ?: null,
            'ip_address'  => Request::ip(),
            'user_agent'  => Request::userAgent(),
            'url'         => Request::fullUrl(),
        ]);
    }
}
