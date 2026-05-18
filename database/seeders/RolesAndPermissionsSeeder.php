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

        // Commercial — ventes + clients
        $commercial = Role::firstOrCreate(['name' => 'commercial', 'guard_name' => 'web']);
        $commercial->syncPermissions([
            'products.view', 'clients.view', 'clients.create', 'clients.edit',
            'quotes.view', 'quotes.create', 'quotes.edit', 'quotes.validate',
            'orders.view', 'orders.create', 'orders.edit', 'orders.validate',
            'invoices.view', 'invoices.create', 'invoices.send',
            'deliveries.view', 'deliveries.create', 'deliveries.validate',
            'credit_notes.view', 'credit_notes.create',
            'payments.view', 'reports.view',
        ]);

        // Comptable — factures + trésorerie + rapports
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
