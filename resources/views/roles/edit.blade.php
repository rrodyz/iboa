@extends('layouts.erp')
@section('title', 'Permissions — '.ucfirst(str_replace('_',' ',$role->name)))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('roles.index') }}" class="hover:text-gray-700">Rôles & Permissions</a>
    <span class="mx-1">/</span>
    <a href="{{ route('roles.show', $role) }}" class="hover:text-gray-700">{{ ucfirst(str_replace('_',' ',$role->name)) }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
@php $currentPerms = $role->permissions->pluck('id')->toArray(); @endphp

<div class="space-y-1 mb-5">
    <h1 class="text-2xl font-bold text-gray-900">
        Permissions &mdash;
        <span class="text-indigo-600">{{ ucfirst(str_replace('_',' ',$role->name)) }}</span>
    </h1>
    <p class="text-sm text-gray-500">Cochez les permissions à accorder à ce rôle.</p>
</div>

<form method="POST" action="{{ route('roles.update', $role) }}">
    @csrf @method('PUT')

    <div class="space-y-4">
        @foreach($grouped as $module => $permissions)
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden"
             x-data="{ allChecked: {{ $permissions->every(fn($p) => in_array($p->id, $currentPerms)) ? 'true' : 'false' }} }">

            {{-- Module header avec toggle tout --}}
            <div class="flex items-center justify-between px-5 py-3.5 bg-gray-50 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-700">{{ $module }}</h3>
                <label class="flex items-center gap-2 cursor-pointer text-xs text-gray-500 hover:text-gray-700 select-none">
                    <input type="checkbox"
                           x-model="allChecked"
                           @change="$el.closest('.bg-white').querySelectorAll('input[type=checkbox][name]').forEach(cb => cb.checked = allChecked)"
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    Tout cocher / décocher
                </label>
            </div>

            {{-- Permissions du module --}}
            <div class="px-5 py-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                @foreach($permissions as $perm)
                @php
                    $action = explode('.', $perm->name)[1] ?? $perm->name;
                    $actionLabels = [
                        'view'     => ['label' => 'Voir',       'color' => 'text-gray-600',  'dot' => 'bg-gray-400'],
                        'create'   => ['label' => 'Créer',      'color' => 'text-green-700', 'dot' => 'bg-green-500'],
                        'edit'     => ['label' => 'Modifier',   'color' => 'text-blue-700',  'dot' => 'bg-blue-500'],
                        'delete'   => ['label' => 'Supprimer',  'color' => 'text-red-700',   'dot' => 'bg-red-500'],
                        'validate' => ['label' => 'Valider',    'color' => 'text-purple-700','dot' => 'bg-purple-500'],
                        'manage'   => ['label' => 'Gérer',      'color' => 'text-indigo-700','dot' => 'bg-indigo-500'],
                        'adjust'   => ['label' => 'Ajuster',    'color' => 'text-orange-700','dot' => 'bg-orange-500'],
                        'transfer' => ['label' => 'Transférer', 'color' => 'text-teal-700',  'dot' => 'bg-teal-500'],
                        'send'     => ['label' => 'Envoyer',    'color' => 'text-sky-700',   'dot' => 'bg-sky-500'],
                        'export'   => ['label' => 'Exporter',   'color' => 'text-amber-700', 'dot' => 'bg-amber-500'],
                    ];
                    $style = $actionLabels[$action] ?? ['label' => $action, 'color' => 'text-gray-600', 'dot' => 'bg-gray-400'];
                @endphp
                <label class="flex items-center gap-2.5 p-2.5 rounded-lg border cursor-pointer transition-colors
                    {{ in_array($perm->id, $currentPerms) ? 'bg-indigo-50 border-indigo-200' : 'bg-white border-gray-200 hover:bg-gray-50' }}
                    has-[:checked]:bg-indigo-50 has-[:checked]:border-indigo-200">
                    <input type="checkbox" name="permissions[]" value="{{ $perm->id }}"
                           {{ in_array($perm->id, $currentPerms) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 flex-shrink-0">
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full {{ $style['dot'] }} flex-shrink-0"></span>
                            <span class="text-xs font-semibold {{ $style['color'] }}">{{ $style['label'] }}</span>
                        </div>
                        <p class="text-xs text-gray-400 font-mono truncate mt-0.5">{{ $perm->name }}</p>
                    </div>
                </label>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    {{-- Boutons --}}
    <div class="mt-5 flex items-center justify-end gap-3">
        <a href="{{ route('roles.show', $role) }}"
           class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
            Annuler
        </a>
        <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
            Enregistrer les permissions
        </button>
    </div>
</form>
@endsection
