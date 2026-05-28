@extends('layouts.erp')
@section('title', 'Rôles & Permissions')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Rôles & Permissions</span>
@endsection

@section('content')
<div class="space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Rôles & Permissions</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $roles->count() }} rôle(s) configurés</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        @foreach($roles as $role)
        @php
            $roleColors = [
                // Rôles système
                'super_admin'            => ['bg' => 'bg-purple-50',  'border' => 'border-purple-200',  'badge' => 'bg-purple-100 text-purple-700',  'icon' => 'text-purple-500'],
                'directeur'              => ['bg' => 'bg-blue-50',    'border' => 'border-blue-200',    'badge' => 'bg-blue-100 text-blue-700',      'icon' => 'text-blue-500'],
                'commercial'             => ['bg' => 'bg-green-50',   'border' => 'border-green-200',   'badge' => 'bg-green-100 text-green-700',    'icon' => 'text-green-500'],
                'responsable_commercial' => ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'badge' => 'bg-emerald-100 text-emerald-700','icon' => 'text-emerald-500'],
                'comptable'              => ['bg' => 'bg-amber-50',   'border' => 'border-amber-200',   'badge' => 'bg-amber-100 text-amber-700',    'icon' => 'text-amber-500'],
                'magasinier'             => ['bg' => 'bg-teal-50',    'border' => 'border-teal-200',    'badge' => 'bg-teal-100 text-teal-700',      'icon' => 'text-teal-500'],
                'responsable_stock'      => ['bg' => 'bg-cyan-50',    'border' => 'border-cyan-200',    'badge' => 'bg-cyan-100 text-cyan-700',      'icon' => 'text-cyan-500'],
                'caissier'               => ['bg' => 'bg-orange-50',  'border' => 'border-orange-200',  'badge' => 'bg-orange-100 text-orange-700',  'icon' => 'text-orange-500'],
                'lecture_seule'          => ['bg' => 'bg-gray-50',    'border' => 'border-gray-200',    'badge' => 'bg-gray-100 text-gray-600',      'icon' => 'text-gray-400'],
                // Rôles RH
                'drh'                    => ['bg' => 'bg-rose-50',    'border' => 'border-rose-200',    'badge' => 'bg-rose-100 text-rose-700',      'icon' => 'text-rose-500'],
                'rh_manager'             => ['bg' => 'bg-pink-50',    'border' => 'border-pink-200',    'badge' => 'bg-pink-100 text-pink-700',      'icon' => 'text-pink-500'],
                'rh_agent'               => ['bg' => 'bg-fuchsia-50', 'border' => 'border-fuchsia-200', 'badge' => 'bg-fuchsia-100 text-fuchsia-700','icon' => 'text-fuchsia-500'],
                'employe'                => ['bg' => 'bg-violet-50',  'border' => 'border-violet-200',  'badge' => 'bg-violet-100 text-violet-700',  'icon' => 'text-violet-500'],
            ];
            $c = $roleColors[$role->name] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'badge' => 'bg-gray-100 text-gray-700', 'icon' => 'text-gray-500'];
            $roleLabels = [
                'super_admin'            => 'Super Administrateur',
                'directeur'              => 'Directeur',
                'commercial'             => 'Commercial',
                'responsable_commercial' => 'Resp. Commercial',
                'comptable'              => 'Comptable',
                'magasinier'             => 'Magasinier',
                'responsable_stock'      => 'Resp. Stock',
                'caissier'               => 'Caissier',
                'lecture_seule'          => 'Lecture seule',
                'drh'                    => 'Directeur RH',
                'rh_manager'             => 'Gestionnaire RH',
                'rh_agent'               => 'Agent RH',
                'employe'                => 'Employé',
            ];
            $isRhRole = in_array($role->name, ['drh','rh_manager','rh_agent','employe']);
        @endphp
        <div class="bg-white rounded-xl border {{ $c['border'] }} p-5 space-y-4">
            <div class="flex items-start justify-between">
                <div class="flex flex-col gap-1">
                    @if($isRhRole)
                    <span class="inline-flex items-center gap-1 text-xs font-medium text-rose-500 mb-0.5">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Module RH
                    </span>
                    @endif
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-sm font-semibold {{ $c['badge'] }}">
                        {{ $roleLabels[$role->name] ?? ucfirst(str_replace('_', ' ', $role->name)) }}
                    </span>
                </div>
                <a href="{{ route('roles.edit', $role) }}"
                   class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors" title="Modifier les permissions">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </a>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="text-center p-3 {{ $c['bg'] }} rounded-lg">
                    <p class="text-2xl font-bold text-gray-900">{{ $role->permissions_count }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">permissions</p>
                </div>
                <div class="text-center p-3 bg-gray-50 rounded-lg">
                    <p class="text-2xl font-bold text-gray-900">{{ $role->users_count }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">utilisateur(s)</p>
                </div>
            </div>

            <div class="flex gap-2">
                <a href="{{ route('roles.show', $role) }}"
                   class="flex-1 text-center text-sm text-gray-600 hover:text-indigo-600 border border-gray-200 hover:border-indigo-300 rounded-lg py-2 transition-colors">
                    Voir le détail
                </a>
                <a href="{{ route('roles.edit', $role) }}"
                   class="flex-1 text-center text-sm text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg py-2 transition-colors">
                    Gérer
                </a>
            </div>
        </div>
        @endforeach
    </div>

</div>
@endsection
