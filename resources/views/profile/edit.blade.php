@extends('layouts.erp')
@section('title', 'Mon profil')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Mon profil</span>
@endsection

@section('content')
<div class="space-y-1 mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Mon profil</h1>
    <p class="text-sm text-gray-500">Gérez vos informations personnelles et la sécurité de votre compte.</p>
</div>

<div class="max-w-2xl space-y-6">

    {{-- ── Informations du profil ──────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="mb-5">
            <h2 class="text-base font-semibold text-gray-900">Informations du profil</h2>
            <p class="text-sm text-gray-500 mt-0.5">Mettez à jour votre nom et votre adresse e-mail.</p>
        </div>

        <form id="send-verification" method="POST" action="{{ route('verification.send') }}">@csrf</form>

        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label for="name" class="block text-xs font-medium text-gray-700 mb-1">Nom complet</label>
                <input id="name" name="name" type="text"
                       value="{{ old('name', $user->name) }}"
                       required autofocus autocomplete="name"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('name')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="block text-xs font-medium text-gray-700 mb-1">Adresse e-mail</label>
                <input id="email" name="email" type="email"
                       value="{{ old('email', $user->email) }}"
                       required autocomplete="username"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('email')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <div class="mt-2 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                        <p class="text-xs text-amber-700">
                            Votre adresse e-mail n'est pas vérifiée.
                            <button form="send-verification"
                                    class="underline font-medium hover:text-amber-900 ml-1">
                                Renvoyer l'e-mail de vérification
                            </button>
                        </p>
                        @if (session('status') === 'verification-link-sent')
                            <p class="mt-1 text-xs font-medium text-green-600">
                                Un nouveau lien de vérification a été envoyé.
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-3 pt-1">
                <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                    Enregistrer
                </button>
                @if (session('status') === 'profile-updated')
                    <p x-data="{ show: true }" x-show="show" x-transition
                       x-init="setTimeout(() => show = false, 2000)"
                       class="text-sm text-emerald-600 font-medium">
                        ✓ Modifications enregistrées.
                    </p>
                @endif
            </div>
        </form>
    </div>

    {{-- ── Modifier le mot de passe ────────────────────────────────────────── --}}
    <div id="password" class="bg-white rounded-xl border border-gray-200 p-6 scroll-mt-20">
        <div class="mb-5">
            <h2 class="text-base font-semibold text-gray-900">Modifier le mot de passe</h2>
            <p class="text-sm text-gray-500 mt-0.5">Utilisez un mot de passe long et aléatoire pour sécuriser votre compte.</p>
        </div>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="update_password_current_password" class="block text-xs font-medium text-gray-700 mb-1">
                    Mot de passe actuel
                </label>
                <input id="update_password_current_password" name="current_password" type="password"
                       autocomplete="current-password"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('current_password', 'updatePassword')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="update_password_password" class="block text-xs font-medium text-gray-700 mb-1">
                    Nouveau mot de passe
                </label>
                <input id="update_password_password" name="password" type="password"
                       autocomplete="new-password"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('password', 'updatePassword')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="update_password_password_confirmation" class="block text-xs font-medium text-gray-700 mb-1">
                    Confirmer le mot de passe
                </label>
                <input id="update_password_password_confirmation" name="password_confirmation" type="password"
                       autocomplete="new-password"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('password_confirmation', 'updatePassword')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-3 pt-1">
                <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                    Mettre à jour
                </button>
                @if (session('status') === 'password-updated')
                    <p x-data="{ show: true }" x-show="show" x-transition
                       x-init="setTimeout(() => show = false, 2000)"
                       class="text-sm text-emerald-600 font-medium">
                        ✓ Mot de passe mis à jour.
                    </p>
                @endif
            </div>
        </form>
    </div>

    {{-- ── Supprimer le compte ─────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-red-200 p-6" x-data="{ open: false }">
        <div class="mb-5">
            <h2 class="text-base font-semibold text-red-700">Supprimer le compte</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                Une fois votre compte supprimé, toutes ses données seront définitivement effacées.
            </p>
        </div>

        <button type="button" @click="open = true"
                class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
            Supprimer le compte
        </button>

        {{-- Modal de confirmation --}}
        <div x-show="open" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">

            <div class="absolute inset-0 bg-black/40" @click="open = false"></div>

            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6 z-10"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">

                <div class="flex items-start gap-4 mb-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Confirmer la suppression</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            Cette action est irréversible. Entrez votre mot de passe pour confirmer.
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('profile.destroy') }}" class="space-y-4">
                    @csrf
                    @method('DELETE')

                    <div>
                        <label for="del_password" class="block text-xs font-medium text-gray-700 mb-1">
                            Mot de passe
                        </label>
                        <input id="del_password" name="password" type="password"
                               placeholder="Votre mot de passe actuel"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        @error('password', 'userDeletion')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-3 pt-1">
                        <button type="button" @click="open = false"
                                class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                            Annuler
                        </button>
                        <button type="submit"
                                class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                            Supprimer définitivement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
