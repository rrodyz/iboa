@props([
    'model' => null,            // ex: App\Models\Quote::class
    'id' => null,               // id de l'entité
    'limit' => 20,
    'title' => 'Historique d\'activité',
])
@php
    $modelClass = is_string($model) ? $model : (is_object($model) ? get_class($model) : null);
    $logs = $modelClass && $id
        ? \App\Models\AuditLog::where('model_type', $modelClass)
            ->where('model_id', $id)
            ->latest('created_at')
            ->limit($limit)
            ->get()
        : collect();

    $actionLabel = function(string $action) {
        if (str_starts_with($action, 'approval_')) {
            $sub = substr($action, 9);
            return [
                'en_attente' => '🟡 Soumis à approbation',
                'approuve'   => '✅ Approuvé',
                'rejete'     => '🛑 Rejeté',
                'non_requis' => '⚪ Approbation non requise',
            ][$sub] ?? '🔄 ' . $sub;
        }
        return [
            'created'         => '➕ Créé',
            'updated'         => '✏️ Modifié',
            'deleted'         => '🗑️ Supprimé',
            'restored'        => '♻️ Restauré',
            'status_changed'  => '🔄 Changement de statut',
            'validated'       => '✅ Validé',
            'paid'            => '💰 Payé',
            'payment_created' => '💰 Paiement enregistré',
            'payment_cancelled' => '↩️ Paiement annulé',
            'payment_modified'  => '✏️ Paiement modifié',
            'journal_entry_created' => '📒 Écriture comptable créée',
            'stock_movement'  => '📦 Mouvement de stock',
            'stock_movement_modified' => '📦 Mouvement modifié',
        ][$action] ?? '🔄 ' . $action;
    };
@endphp

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-700">{{ $title }}</h2>
        <span class="text-xs text-gray-400">{{ $logs->count() }} événement{{ $logs->count() > 1 ? 's' : '' }}</span>
    </div>

    @if($logs->isEmpty())
        <div class="p-6 text-center text-gray-400 text-sm">Aucune activité enregistrée.</div>
    @else
    <ol class="relative px-5 py-4 space-y-3">
        @foreach($logs as $log)
            <li class="relative pl-8">
                <div class="absolute left-0 top-1 w-5 h-5 rounded-full bg-violet-100 flex items-center justify-center">
                    <span class="w-2 h-2 rounded-full bg-violet-500"></span>
                </div>
                @if(!$loop->last)
                    <div class="absolute left-2 top-6 bottom-[-12px] w-px bg-gray-200"></div>
                @endif
                <div class="flex flex-col sm:flex-row sm:items-baseline sm:gap-2">
                    <span class="text-sm font-medium text-gray-900">{{ $actionLabel($log->action) }}</span>
                    <span class="text-xs text-gray-500">par <span class="font-medium">{{ $log->user_name ?? 'Système' }}</span></span>
                    <span class="text-xs text-gray-400 sm:ml-auto" title="{{ $log->created_at }}">
                        {{ $log->created_at?->diffForHumans() }}
                    </span>
                </div>

                @if($log->new_values || $log->old_values)
                    @php
                        $newValues = is_array($log->new_values) ? $log->new_values : (json_decode($log->new_values ?? '[]', true) ?: []);
                        $oldValues = is_array($log->old_values) ? $log->old_values : (json_decode($log->old_values ?? '[]', true) ?: []);
                        $changes = [];
                        foreach ($newValues as $k => $v) {
                            if (in_array($k, ['updated_at','created_at'])) continue;
                            $oldVal = $oldValues[$k] ?? null;
                            $changes[$k] = ['old' => $oldVal, 'new' => $v];
                        }
                    @endphp
                    @if(!empty($changes))
                    <div class="mt-1 text-xs text-gray-600 space-y-0.5">
                        @foreach($changes as $field => $pair)
                            <div>
                                <span class="font-mono text-gray-500">{{ $field }}</span>
                                @if($log->action !== 'created' && $log->action !== 'deleted')
                                    : <span class="line-through text-red-500">{{ is_scalar($pair['old']) ? \Str::limit((string) $pair['old'], 40) : json_encode($pair['old']) }}</span>
                                    →
                                @endif
                                <span class="text-emerald-700 font-medium">{{ is_scalar($pair['new']) ? \Str::limit((string) $pair['new'], 40) : json_encode($pair['new']) }}</span>
                            </div>
                        @endforeach
                    </div>
                    @endif
                @endif
            </li>
        @endforeach
    </ol>
    @endif
</div>
