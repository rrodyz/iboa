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
                // Rôles Direction
                'super_admin'            => ['bg' => 'bg-purple-50',  'border' => 'border-purple-200',  'badge' => 'bg-purple-100 text-purple-700',  'icon' => 'text-purple-500'],
                'directeur'              => ['bg' => 'bg-blue-50',    'border' => 'border-blue-200',    'badge' => 'bg-blue-100 text-blue-700',      'icon' => 'text-blue-500'],
                'daf'                    => ['bg' => 'bg-indigo-50',  'border' => 'border-indigo-200',  'badge' => 'bg-indigo-100 text-indigo-700',  'icon' => 'text-indigo-500'],
                // Rôles Commercial
                'commercial'             => ['bg' => 'bg-green-50',   'border' => 'border-green-200',   'badge' => 'bg-green-100 text-green-700',    'icon' => 'text-green-500'],
                'responsable_commercial' => ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'badge' => 'bg-emerald-100 text-emerald-700','icon' => 'text-emerald-500'],
                // Rôles Finance / Compta
                'comptable'              => ['bg' => 'bg-amber-50',   'border' => 'border-amber-200',   'badge' => 'bg-amber-100 text-amber-700',    'icon' => 'text-amber-500'],
                // Rôles Achats
                'acheteur'               => ['bg' => 'bg-orange-50',  'border' => 'border-orange-200',  'badge' => 'bg-orange-100 text-orange-700',  'icon' => 'text-orange-500'],
                // Rôles Stock / Logistique
                'magasinier'             => ['bg' => 'bg-teal-50',    'border' => 'border-teal-200',    'badge' => 'bg-teal-100 text-teal-700',      'icon' => 'text-teal-500'],
                'responsable_stock'      => ['bg' => 'bg-cyan-50',    'border' => 'border-cyan-200',    'badge' => 'bg-cyan-100 text-cyan-700',      'icon' => 'text-cyan-500'],
                'caissier'               => ['bg' => 'bg-yellow-50',  'border' => 'border-yellow-200',  'badge' => 'bg-yellow-100 text-yellow-700',  'icon' => 'text-yellow-500'],
                // Rôles Production (§15 CDC)
                'chef_production'        => ['bg' => 'bg-sky-50',     'border' => 'border-sky-200',     'badge' => 'bg-sky-100 text-sky-700',        'icon' => 'text-sky-500'],
                'directeur_usine'        => ['bg' => 'bg-blue-50',    'border' => 'border-blue-200',    'badge' => 'bg-blue-100 text-blue-700',      'icon' => 'text-blue-500'],
                'operateur_production'   => ['bg' => 'bg-slate-50',   'border' => 'border-slate-200',   'badge' => 'bg-slate-100 text-slate-700',    'icon' => 'text-slate-500'],
                // Rôles Qualité (§15 CDC)
                'responsable_qualite'    => ['bg' => 'bg-lime-50',    'border' => 'border-lime-200',    'badge' => 'bg-lime-100 text-lime-700',      'icon' => 'text-lime-500'],
                // Rôles Maintenance (§15 CDC)
                'technicien_maintenance' => ['bg' => 'bg-zinc-50',    'border' => 'border-zinc-200',    'badge' => 'bg-zinc-100 text-zinc-700',      'icon' => 'text-zinc-500'],
                // Divers
                'lecture_seule'          => ['bg' => 'bg-gray-50',    'border' => 'border-gray-200',    'badge' => 'bg-gray-100 text-gray-600',      'icon' => 'text-gray-400'],
                // Rôles RH
                'drh'                    => ['bg' => 'bg-rose-50',    'border' => 'border-rose-200',    'badge' => 'bg-rose-100 text-rose-700',      'icon' => 'text-rose-500'],
                'rh_manager'             => ['bg' => 'bg-pink-50',    'border' => 'border-pink-200',    'badge' => 'bg-pink-100 text-pink-700',      'icon' => 'text-pink-500'],
                'rh_agent'               => ['bg' => 'bg-fuchsia-50', 'border' => 'border-fuchsia-200', 'badge' => 'bg-fuchsia-100 text-fuchsia-700','icon' => 'text-fuchsia-500'],
                'employe'                => ['bg' => 'bg-violet-50',  'border' => 'border-violet-200',  'badge' => 'bg-violet-100 text-violet-700',  'icon' => 'text-violet-500'],
            ];
            $c = $roleColors[$role->name] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'badge' => 'bg-gray-100 text-gray-700', 'icon' => 'text-gray-500'];
            $roleLabels = [
                // Direction
                'super_admin'            => 'Super Administrateur',
                'directeur'              => 'Directeur Général (DG)',
                'daf'                    => 'DAF — Dir. Administratif & Financier',
                // Commercial
                'commercial'             => 'Commercial',
                'responsable_commercial' => 'Resp. Commercial',
                // Finance
                'comptable'              => 'Comptable',
                // Achats (§15 CDC)
                'acheteur'               => 'Acheteur / Approvisionneur',
                // Stock
                'magasinier'             => 'Magasinier',
                'responsable_stock'      => 'Resp. Stock',
                'caissier'               => 'Caissier',
                // Production (§15 CDC)
                'chef_production'        => 'Chef de Production',
                'directeur_usine'        => 'Directeur d\'Usine',
                'operateur_production'   => 'Opérateur de Production',
                // Qualité (§15 CDC)
                'responsable_qualite'    => 'Responsable Qualité',
                // Maintenance (§15 CDC)
                'technicien_maintenance' => 'Technicien Maintenance',
                // Divers
                'lecture_seule'          => 'Lecture seule',
                // RH
                'drh'                    => 'Directeur RH',
                'rh_manager'             => 'Gestionnaire RH',
                'rh_agent'               => 'Agent RH',
                'employe'                => 'Employé',
            ];
            // Badge de catégorie par rôle
            $roleCategoryBadge = [
                'super_admin' => null, 'directeur' => null, 'daf' => ['Direction', 'text-indigo-400'],
                'commercial' => ['Ventes', 'text-green-400'], 'responsable_commercial' => ['Ventes', 'text-green-400'],
                'comptable' => ['Finance', 'text-amber-400'], 'acheteur' => ['Achats', 'text-orange-400'],
                'magasinier' => ['Stock', 'text-teal-400'], 'responsable_stock' => ['Stock', 'text-teal-400'],
                'caissier' => ['Trésorerie', 'text-yellow-400'],
                'chef_production' => ['Production', 'text-sky-400'], 'directeur_usine' => ['Production', 'text-sky-400'],
                'operateur_production' => ['Production', 'text-sky-400'],
                'responsable_qualite' => ['Qualité', 'text-lime-400'],
                'technicien_maintenance' => ['Maintenance', 'text-zinc-400'],
                'drh' => ['RH', 'text-rose-400'], 'rh_manager' => ['RH', 'text-rose-400'],
                'rh_agent' => ['RH', 'text-rose-400'], 'employe' => ['RH', 'text-rose-400'],
            ];
            $catBadge = $roleCategoryBadge[$role->name] ?? null;
            $isRhRole = in_array($role->name, ['drh','rh_manager','rh_agent','employe']);
        @endphp
        <div class="bg-white rounded-xl border {{ $c['border'] }} p-5 space-y-4">
            <div class="flex items-start justify-between">
                <div class="flex flex-col gap-1">
                    @if($catBadge)
                    <span class="inline-flex items-center gap-1 text-xs font-medium {{ $catBadge[1] }} mb-0.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                        {{ $catBadge[0] }}
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
