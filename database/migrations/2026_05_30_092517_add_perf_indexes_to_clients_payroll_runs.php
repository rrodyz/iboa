<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [PERF] Index manquants détectés lors de l'audit de performance.
 *
 * payroll_runs     : (company_id, status) — filtre status lent sans index combiné
 * crm_opportunities: (company_id, stage) — GROUP BY du dashboard CRM
 * crm_contacts     : (company_id, created_at) — newThisMonth count
 * journal_entries  : (company_id, status, entry_date) — loadAccountsWithMovements JOIN
 *
 * Note : clients et products n'ont pas de company_id (référentiel global partagé).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── payroll_runs(company_id, status) ─────────────────────────────────
        // PayrollRunController@index filtre par status ; l'index existant
        // (company_id, period_year, period_month) ne couvre pas status.
        if (! $this->indexExists('payroll_runs', 'idx_pr_company_status')) {
            Schema::table('payroll_runs', function (Blueprint $table) {
                $table->index(['company_id', 'status'], 'idx_pr_company_status');
            });
        }

        // ── crm_opportunities(company_id, stage) ──────────────────────────────
        // CrmDashboardController GROUP BY stage — un seul scan d'index suffit
        if (Schema::hasTable('crm_opportunities') && ! $this->indexExists('crm_opportunities', 'idx_crm_opp_company_stage')) {
            Schema::table('crm_opportunities', function (Blueprint $table) {
                $table->index(['company_id', 'stage'], 'idx_crm_opp_company_stage');
            });
        }

        // ── crm_contacts(company_id, created_at) ──────────────────────────────
        // CrmDashboardController::newThisMonth WHERE + DATE range
        if (Schema::hasTable('crm_contacts') && ! $this->indexExists('crm_contacts', 'idx_crm_contacts_company_created')) {
            Schema::table('crm_contacts', function (Blueprint $table) {
                $table->index(['company_id', 'created_at'], 'idx_crm_contacts_company_created');
            });
        }

        // ── journal_entries(company_id, status, entry_date) ───────────────────
        // loadAccountsWithMovements JOIN : l'index existant (company_id, status)
        // ne couvre pas entry_date → la range-condition sur la date fait un full
        // scan de l'index partiel. Cet index composite résout le problème.
        if (! $this->indexExists('journal_entries', 'idx_je_status_date_company')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->index(['company_id', 'status', 'entry_date'], 'idx_je_status_date_company');
            });
        }
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function ($t) {
            if (Schema::hasIndex('payroll_runs', 'idx_pr_company_status'))
                $t->dropIndex('idx_pr_company_status');
        });

        if (Schema::hasTable('crm_opportunities')) {
            Schema::table('crm_opportunities', function ($t) {
                if (Schema::hasIndex('crm_opportunities', 'idx_crm_opp_company_stage'))
                    $t->dropIndex('idx_crm_opp_company_stage');
            });
        }
        if (Schema::hasTable('crm_contacts')) {
            Schema::table('crm_contacts', function ($t) {
                if (Schema::hasIndex('crm_contacts', 'idx_crm_contacts_company_created'))
                    $t->dropIndex('idx_crm_contacts_company_created');
            });
        }
        Schema::table('journal_entries', function ($t) {
            if (Schema::hasIndex('journal_entries', 'idx_je_status_date_company'))
                $t->dropIndex('idx_je_status_date_company');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return Schema::hasIndex($table, $indexName);
    }
};
