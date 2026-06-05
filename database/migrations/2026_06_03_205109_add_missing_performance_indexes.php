<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index de performance manquants — 10 index couvrant les requêtes critiques.
 * Chaque index justifié par son pattern de requête ERP.
 */
return new class extends Migration
{
    public function up(): void
    {
        // fiscal_years.company_id — filtres rapports + future multi-tenant
        if (Schema::hasColumn('fiscal_years', 'company_id')
            && !$this->has('fiscal_years', 'fy_company_id_idx')) {
            Schema::table('fiscal_years', fn(Blueprint $t) =>
                $t->index('company_id', 'fy_company_id_idx'));
        }

        // payment_terms — scope systématique CompanyScope
        if (Schema::hasColumn('payment_terms', 'company_id')
            && !$this->has('payment_terms', 'pt_company_id_idx')) {
            Schema::table('payment_terms', fn(Blueprint $t) =>
                $t->index('company_id', 'pt_company_id_idx'));
        }

        // suppliers — scope + recherche par nom
        if (Schema::hasColumn('suppliers', 'company_id')
            && !$this->has('suppliers', 'sup_company_id_idx')) {
            Schema::table('suppliers', function (Blueprint $t) {
                $t->index('company_id', 'sup_company_id_idx');
                $t->index('name',       'sup_name_idx');
            });
        }

        // clients — scope systématique
        if (Schema::hasColumn('clients', 'company_id')
            && !$this->has('clients', 'cli_company_id_idx')) {
            Schema::table('clients', fn(Blueprint $t) =>
                $t->index('company_id', 'cli_company_id_idx'));
        }

        // tax_rates — WHERE type='tva' AND is_active=1 très fréquent
        if (!$this->has('tax_rates', 'tr_type_active_idx')) {
            Schema::table('tax_rates', fn(Blueprint $t) =>
                $t->index(['type','is_active'], 'tr_type_active_idx'));
        }

        // products — filtres catalogue is_stockable / is_sellable / is_active
        if (!$this->has('products', 'prod_flags_idx')) {
            Schema::table('products', fn(Blueprint $t) =>
                $t->index(['is_stockable','is_sellable','is_active'], 'prod_flags_idx'));
        }

        // journal_entries.reference — lookup par numéro document (FEC, grand-livre, TVA)
        if (!$this->has('journal_entries', 'je_reference_idx')) {
            Schema::table('journal_entries', fn(Blueprint $t) =>
                $t->index('reference', 'je_reference_idx'));
        }

        // crm_activities — "mes activités", overdue, agenda
        if (Schema::hasTable('crm_activities')
            && !$this->has('crm_activities', 'ca_user_due_done_idx')) {
            Schema::table('crm_activities', fn(Blueprint $t) =>
                $t->index(['user_id','due_at','is_done'], 'ca_user_due_done_idx'));
        }

        // audit_logs.company_id — journal d'audit filtré par société
        if (Schema::hasTable('audit_logs')
            && Schema::hasColumn('audit_logs','company_id')
            && !$this->has('audit_logs', 'al_company_id_idx')) {
            Schema::table('audit_logs', fn(Blueprint $t) =>
                $t->index('company_id', 'al_company_id_idx'));
        }
    }

    public function down(): void
    {
        $drops = [
            ['fiscal_years',    'fy_company_id_idx'],
            ['payment_terms',   'pt_company_id_idx'],
            ['suppliers',       'sup_company_id_idx'],
            ['suppliers',       'sup_name_idx'],
            ['clients',         'cli_company_id_idx'],
            ['tax_rates',       'tr_type_active_idx'],
            ['products',        'prod_flags_idx'],
            ['journal_entries', 'je_reference_idx'],
            ['crm_activities',  'ca_user_due_done_idx'],
            ['audit_logs',      'al_company_id_idx'],
        ];
        foreach ($drops as [$table, $idx]) {
            if (Schema::hasTable($table) && $this->has($table, $idx)) {
                Schema::table($table, fn(Blueprint $t) => $t->dropIndex($idx));
            }
        }
    }

    private function has(string $table, string $name): bool
    {
        // Introspection portable (MySQL + SQLite tests) — évite le SHOW INDEX MySQL-only.
        foreach (Schema::getIndexes($table) as $idx) {
            if (($idx['name'] ?? null) === $name) {
                return true;
            }
        }
        return false;
    }
};
