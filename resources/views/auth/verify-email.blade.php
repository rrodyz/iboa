<x-guest-layout>

    {{-- Succès renvoi --}}
    @if (session('status') == 'verification-link-sent')
    <div class="flex items-center gap-2.5 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm mb-6">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        Un nouveau lien de vérification a été envoyé à votre adresse e-mail.
    </div>
    @endif

    {{-- Heading --}}
    <div class="mb-8 text-center">
        <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-5">
            <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </div>
        <h2 class="text-2xl font-bold text-gray-900">Vérifiez votre e-mail</h2>
        <p class="text-gray-500 text-sm mt-2 leading-relaxed max-w-xs mx-auto">
            Merci de votre inscription ! Cliquez sur le lien que nous venons d'envoyer à votre adresse e-mail pour activer votre compte.
        </p>
    </div>

    {{-- Actions --}}
    <div class="space-y-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn-primary">
                Renvoyer l'e-mail de vérification
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="w-full py-2.5 px-4 text-sm text-gray-600 hover:text-gray-900 font-medium border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors">
                Se déconnecter
            </button>
        </form>
    </div>

    <p class="text-center text-xs text-gray-400 mt-6">
        Vous n'avez pas reçu l'e-mail ? Vérifiez vos spams ou cliquez sur « Renvoyer ».
    </p>

</x-guest-layout>
