@extends('layouts.erp')
@section('title', 'Rôle '.ucfirst(str_replace('_',' ',$role->name)))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('roles.index') }}" class="hover:text-gray-700">Rôles & Permissions</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ ucfirst(str_replace('_',' ',$role->name)) }}</span>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ ucfirst(str_replace('_',' ',$role->name)) }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $role->permissions->count() }} permissions &bull; {{ $users->count() }} utilisateur(s)</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('roles.edit', $role) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Modifier les permissions
            </a>
            <a href="{{ route('roles.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                Retour
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Permissions --}}
        <div class="lg:col-span-2 space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Permissions accordées</h2>
            @php $rolePerms = $role->permissions->pluck('name')->toArray(); @endphp
            @foreach($grouped as $module => $permissions)
            @php $moduleGranted = $permissions->filter(fn($p) => in_array($p->name, $rolePerms)); @endphp
            @if($moduleGranted->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">{{ $module }}</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($moduleGranted as $perm)
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ $perm->name }}
                    </span>
                    @endforeach
                </div>
            </div>
            @endif
            @endforeach

            {{-- Permissions refusées --}}
            @php
                $allPerms = collect($grouped)->flatten()->pluck('name')->toArray();
                $denied   = array_diff($allPerms, $rolePerms);
            @endphp
            @if(!empty($denied))
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Non accordées</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($denied as $p)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-gray-50 text-gray-400 border border-gray-200">
                        {{ $p }}
                    </span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Utilisateurs --}}
        <div class="space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Utilisateurs avec ce rôle</h2>
            <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                @forelse($users as $user)
                <div class="flex items-center gap-3 px-4 py-3">
                    <div class="w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center
                        {{ $user->is_active ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-400' }} font-bold text-xs">
                        {{ strtoupper(substr($user->name, 0, 2)) }}
                    </div>
                    <div class="min-w-0">
                        <a href="{{ route('users.show', $user) }}" class="text-sm font-medium text-gray-900 hover:text-indigo-600 truncate block">
                            {{ $user->name }}
                        </a>
                        <p class="text-xs text-gray-500 truncate">{{ $user->email }}</p>
                    </div>
                    @if(!$user->is_active)
                    <span class="ml-auto text-xs text-gray-400 flex-shrink-0">Inactif</span>
                    @endif
                </div>
                @empty
                <div class="px-4 py-8 text-center text-sm text-gray-400">
                    Aucun utilisateur avec ce rôle.
                </div>
                @endforelse
            </div>
        </div>

    </div>
</div>
@endsection
