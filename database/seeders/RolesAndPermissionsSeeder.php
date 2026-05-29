<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Société
            'company.view', 'company.edit',
            // Articles
            'products.view', 'products.create', 'products.edit', 'products.delete',
            // Clients
            'clients.view', 'clients.create', 'clients.edit', 'clients.delete',
            // Fournisseurs
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
            // Ventes
            'quotes.view', 'quotes.create', 'quotes.edit', 'quotes.delete', 'quotes.validate',
            'orders.view', 'orders.create', 'orders.edit', 'orders.delete', 'orders.validate',
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'invoices.validate', 'invoices.send',
            'deliveries.view', 'deliveries.create', 'deliveries.edit', 'deliveries.validate',
            'credit_notes.view', 'credit_notes.create', 'credit_notes.edit',
            // Workflow de validation interne Ventes (transversal à tous les documents)
            'sales.create',       // créer un document en brouillon
            'sales.submit',       // soumettre à validation interne
            'sales.validate',     // valider (responsable)
            'sales.reject',       // refuser avec motif obligatoire
            'sales.cancel',       // annuler avec motif obligatoire
            'sales.transform',              // transformer (Devis→Commande→BL→Facture→Écriture)
            'sales.view_all',               // voir tous les documents (pas seulement les siens)
            'sales.bypass_self_validation', // valider son propre document (bypass double validation)
            // Achats
            'purchase_requests.view', 'purchase_requests.create', 'purchase_requests.submit', 'purchase_requests.approve',
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit', 'purchase_orders.validate',
            'receptions.view', 'receptions.create', 'receptions.validate',
            'supplier_invoices.view', 'supplier_invoices.create', 'supplier_invoices.edit',
            'supplier_returns.view', 'supplier_returns.create', 'supplier_returns.validate',
            // Stocks
            'stocks.view', 'stocks.adjust', 'stocks.transfer',
            'inventory.view', 'inventory.create', 'inventory.validate',
            // Trésorerie
            'payments.view', 'payments.create', 'payments.edit',
            'cash_accounts.view', 'cash_accounts.manage',
            'treasury.write', 'treasury.validate',
            // Rapports
            'reports.view', 'reports.export',
            // Comptabilité SYSCOHADA
            'accounting.view', 'accounting.write', 'accounting.validate', 'accounting.manage',
            // Admin
            'users.manage', 'roles.manage', 'settings.manage', 'audit.view',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Super Admin — tous les droits
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        // Directeur — tout sauf users.manage, roles.manage
        $directeur = Role::firstOrCreate(['name' => 'directeur', 'guard_name' => 'web']);
        $directeur->syncPermissions(Permission::whereNotIn('name', ['users.manage', 'roles.manage'])->get());

        // Commercial — ventes + clients + workflow (create + submit + transform)
        $commercial = Role::firstOrCreate(['name' => 'commercial', 'guard_name' => 'web']);
        $commercial->syncPermissions([
            'products.view', 'clients.view', 'clients.create', 'clients.edit',
            'quotes.view', 'quotes.create', 'quotes.edit',
            'orders.view', 'orders.create', 'orders.edit',
            'invoices.view', 'invoices.create', 'invoices.send',
            'deliveries.view', 'deliveries.create',
            'credit_notes.view', 'credit_notes.create',
            'payments.view', 'reports.view',
            // Workflow : un commercial crée, soumet et transforme mais ne valide pas
            'sales.create', 'sales.submit', 'sales.transform',
        ]);

        // Comptable — factures + trésorerie + rapports + workflow validation factures/avoirs
        $comptable = Role::firstOrCreate(['name' => 'comptable', 'guard_name' => 'web']);
        $comptable->syncPermissions([
            'products.view', 'clients.view', 'suppliers.view',
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.validate',
            'supplier_invoices.view', 'supplier_invoices.create', 'supplier_invoices.edit',
            'supplier_returns.view', 'supplier_returns.create', 'supplier_returns.validate',
            'credit_notes.view', 'credit_notes.create',
            'payments.view', 'payments.create', 'payments.edit',
            'cash_accounts.view', 'cash_accounts.manage',
            'treasury.write', 'treasury.validate',
            'reports.view', 'reports.export',
            'accounting.view', 'accounting.write', 'accounting.validate', 'accounting.manage',
            // Workflow : le comptable valide les factures et avoirs, peut annuler
            'sales.validate', 'sales.reject', 'sales.cancel', 'sales.view_all',
        ]);

        // Magasinier — stocks + réceptions
        $magasinier = Role::firstOrCreate(['name' => 'magasinier', 'guard_name' => 'web']);
        $magasinier->syncPermissions([
            'products.view', 'stocks.view', 'stocks.adjust', 'stocks.transfer',
            'inventory.view', 'inventory.create', 'inventory.validate',
            'receptions.view', 'receptions.create', 'receptions.validate',
            'supplier_returns.view', 'supplier_returns.create', 'supplier_returns.validate',
            'purchase_orders.view', 'deliveries.view',
        ]);

        $this->command->info('Roles & Permissions créés avec succès.');
    }
}
