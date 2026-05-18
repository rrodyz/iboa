<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Détermine la route d'accueil d'un utilisateur en fonction de ses permissions.
 *
 * L'ordre définit la priorité : le premier module auquel l'utilisateur a accès
 * (permission *.view) devient sa page d'accueil.
 */
class UserHomeRoute
{
    /**
     * Carte permission → route Laravel (dans l'ordre de priorité).
     */
    private const MAP = [
        'dashboard.view'          => 'dashboard',
        'invoices.view'           => 'ventes.factures.index',
        'quotes.view'             => 'ventes.devis.index',
        'orders.view'             => 'ventes.commandes.index',
        'credit_notes.view'       => 'ventes.avoirs.index',
        'deliveries.view'         => 'ventes.bons-livraison.index',
        'clients.view'            => 'clients.index',
        'suppliers.view'          => 'suppliers.index',
        'products.view'           => 'products.index',
        'stocks.view'             => 'stocks.index',
        'inventory.view'          => 'stocks.inventaires.index',
        'payments.view'           => 'tresorerie.encaissements.index',
        'cash_accounts.view'      => 'tresorerie.caisses.index',
        'purchase_orders.view'    => 'achats.commandes.index',
        'purchase_requests.view'  => 'achats.demandes-achat.index',
        'receptions.view'         => 'achats.receptions.index',
        'supplier_invoices.view'  => 'achats.factures-fournisseurs.index',
        'supplier_returns.view'   => 'achats.retours-fournisseurs.index',
        'accounting.view'         => 'comptabilite.journaux.index',
        'reports.view'            => 'reports.index',
        'audit.view'              => 'audit.index',
    ];

    /**
     * Résout la route d'accueil d'un utilisateur.
     *
     * @param  Authenticatable|null  $user
     * @return string  URL absolue vers la page d'accueil
     */
    public static function resolve(?Authenticatable $user = null): string
    {
        $user ??= auth()->user();

        if (! $user) {
            return route('login');
        }

        // Les super-admins vont au dashboard
        if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return route('dashboard');
        }

        foreach (self::MAP as $permission => $routeName) {
            if (method_exists($user, 'can') && $user->can($permission)) {
                return route($routeName);
            }
        }

        // Aucune permission connue → page de profil en dernier recours
        return route('profile.edit');
    }

    /**
     * Retourne true si l'utilisateur peut accéder au tableau de bord.
     */
    public static function canSeeDashboard(?Authenticatable $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return true;
        }

        return method_exists($user, 'can') && $user->can('dashboard.view');
    }
}
