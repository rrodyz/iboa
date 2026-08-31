<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // [A3-UI-V2] Partage minimal — jamais le modèle User complet, jamais
        // les rôles Spatie bruts. Les 201 middleware permission:* restent
        // l'unique autorité ; ceci ne sert qu'à l'UX (masquer/afficher).
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
                // [REACT-01B — fix Phase 1] super_admin autorise via
                // Gate::before() (AppServiceProvider), jamais via des
                // permissions Spatie explicitement assignées au rôle — donc
                // getAllPermissions() renvoie [] pour lui. Sans is_super_admin,
                // le frontend croirait ce rôle sans aucun droit alors que le
                // serveur l'autorise partout. Le mécanisme d'autorisation
                // serveur (Gate/middleware permission:*) n'est pas touché ;
                // ceci n'est qu'un signal UX pour le helper can() côté React.
                'is_super_admin' => fn () => $request->user()?->hasRole('super_admin') ?? false,
                'permissions' => fn () => $request->user()
                    ? $request->user()->getAllPermissions()->pluck('name')->values()
                    : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }
}
