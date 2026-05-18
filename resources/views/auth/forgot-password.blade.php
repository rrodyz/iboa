<x-guest-layout>
<x-slot:heading></x-slot:heading>{{-- page owns its heading --}}

    {{-- Succès envoi --}}
    @if (session('status'))
    <div class="flex items-center gap-2.5 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm mb-6">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        {{ session('status') }}
    </div>
    @endif

    {{-- Heading --}}
    <div class="mb-8">
        <div class="w-12 h-12 bg-indigo-100 rounded-2xl flex items-center justify-center mb-5">
            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
            </svg>
        </div>
        <h2 class="text-2xl font-bold text-gray-900">Mot de passe oublié ?</h2>
        <p class="text-gray-500 text-sm mt-1.5 leading-relaxed">
            Pas de souci. Renseignez votre adresse e-mail et nous vous enverrons un lien de réinitialisation.
        </p>
    </div>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        {{-- Email --}}
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Adresse e-mail</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <svg style="width:1.1rem;height:1.1rem" class="text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                    </svg>
                </span>
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                       required autofocus
                       placeholder="vous@exemple.com"
                       class="input-field @error('email') border-red-400 bg-red-50 @enderror">
            </div>
            @error('email')
            <p class="text-red-500 text-xs mt-1.5">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn-primary">
            Envoyer le lien de réinitialisation
        </button>

        <p class="text-center text-sm text-gray-500">
            <a href="{{ route('login') }}" class="text-indigo-600 hover:text-indigo-700 font-medium transition-colors inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Retour à la connexion
            </a>
        </p>
    </form>

</x-guest-layout>
