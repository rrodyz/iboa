<x-guest-layout>

    {{-- ── Session status ─────────────────────────────────────────────── --}}
    @if (session('status'))
    <div class="g-alert g-alert-ok">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" flex-shrink="0">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        {{ session('status') }}
    </div>
    @endif

    {{-- ── Error ───────────────────────────────────────────────────────── --}}
    @if ($errors->any())
    <div class="g-alert g-alert-err">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" flex-shrink="0">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        Identifiants incorrects. Veuillez réessayer.
    </div>
    @endif

    <form method="POST" action="{{ route('login') }}" style="display:flex; flex-direction:column; gap:20px;">
        @csrf

        {{-- ── Email / Utilisateur ──────────────────────────────────────── --}}
        <div>
            <label for="email" class="g-label">Nom d'utilisateur</label>
            <div class="g-field {{ $errors->has('email') ? 'has-error' : '' }}">
                <span class="g-icon">
                    <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </span>
                <input id="email"
                       type="email"
                       name="email"
                       value="{{ old('email') }}"
                       required
                       autofocus
                       autocomplete="username"
                       placeholder="Nom d'utilisateur"
                       class="g-input">
            </div>
        </div>

        {{-- ── Mot de passe ──────────────────────────────────────────────── --}}
        <div x-data="{ show: false }">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:7px;">
                <label for="password" class="g-label" style="margin-bottom:0;">Mot de passe</label>
                @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="g-forgot">Oublié ?</a>
                @endif
            </div>
            <div class="g-field {{ $errors->has('password') ? 'has-error' : '' }}">
                <span class="g-icon">
                    <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </span>
                <input id="password"
                       :type="show ? 'text' : 'password'"
                       name="password"
                       required
                       autocomplete="current-password"
                       placeholder="Mot de passe"
                       class="g-input" style="padding-right:44px;">
                <button type="button" @click="show = !show" class="g-toggle" tabindex="-1" aria-label="Afficher/masquer">
                    <svg x-show="!show" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg x-show="show" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" style="display:none">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- ── Se souvenir de moi ───────────────────────────────────────── --}}
        <div class="g-check-wrap">
            <input id="remember_me" type="checkbox" name="remember" class="g-check">
            <label for="remember_me" class="g-check-label">Se souvenir de moi</label>
        </div>

        {{-- ── Bouton connexion ─────────────────────────────────────────── --}}
        <button type="submit" class="g-btn">
            SE CONNECTER
        </button>

    </form>

</x-guest-layout>
