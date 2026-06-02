<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [AUDIT DB] Corrections issues de l'audit base de données :
 *
 *  1. Deux clés étrangères manquantes (intégrité référentielle) :
 *     - pay_rubrics.plan_id                       → payroll_plans.id
 *     - external_transactions.client_payment_id   → client_payments.id
 *     (0 orphelin vérifié, types bigint unsigned compatibles, colonnes nullable
 *      → ON DELETE SET NULL est le comportement sûr).
 *
 *  2. Index composites (company_id, status) sur les tables transactionnelles
 *     qui n'avaient qu'un index simple sur company_id. Accélère les listes
 *     filtrées par société ET statut (cas le plus fréquent des écrans ERP).
 *     invoices possède déjà (company_id, status, issued_at) — on aligne les autres.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Clés étrangères manquantes ────────────────────────────────────
        if (Schema::hasColumn('pay_rubrics', 'plan_id')
            && ! $this->hasForeignKey('pay_rubrics', 'plan_id')) {
            Schema::table('pay_rubrics', function (Blueprint $table) {
                $table->foreign('plan_id')
                      ->references('id')->on('payroll_plans')
                      ->nullOnDelete();
            });
        }

        if (Schema::hasColumn('external_transactions', 'client_payment_id')
            && ! $this->hasForeignKey('external_transactions', 'client_payment_id')) {
            Schema::table('external_transactions', function (Blueprint $table) {
                $table->foreign('client_payment_id')
                      ->references('id')->on('client_payments')
                      ->nullOnDelete();
            });
        }

        // ── 2. Index composites (company_id, status) ─────────────────────────
        foreach (['orders', 'quotes', 'purchase_orders'] as $tbl) {
            if (Schema::hasColumn($tbl, 'company_id')
                && Schema::hasColumn($tbl, 'status')
                && ! Schema::hasIndex($tbl, "{$tbl}_company_id_status_index")) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->index(['company_id', 'status']);
                });
            }
        }
    }

    public function down(): void
    {
        if ($this->hasForeignKey('pay_rubrics', 'plan_id')) {
            Schema::table('pay_rubrics', fn (Blueprint $t) => $t->dropForeign(['plan_id']));
        }
        if ($this->hasForeignKey('external_transactions', 'client_payment_id')) {
            Schema::table('external_transactions', fn (Blueprint $t) => $t->dropForeign(['client_payment_id']));
        }

        foreach (['orders', 'quotes', 'purchase_orders'] as $tbl) {
            if (Schema::hasIndex($tbl, "{$tbl}_company_id_status_index")) {
                Schema::table($tbl, fn (Blueprint $t) => $t->dropIndex("{$tbl}_company_id_status_index"));
            }
        }
    }

    /** Vérifie l'existence d'une FK sur une colonne (MySQL via information_schema). */
    private function hasForeignKey(string $table, string $column): bool
    {
        if (\DB::getDriverName() !== 'mysql') {
            return false; // SQLite (tests) : pas d'information_schema FK, on laisse passer
        }
        return \DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', \DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }
};
