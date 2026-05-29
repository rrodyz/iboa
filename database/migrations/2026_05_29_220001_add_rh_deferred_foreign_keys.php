<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration déférée RH — résout les conflits d'ordre dans les groupes
 * à timestamps identiques où la table enfant est créée avant la table parente
 * (ordre alphabétique du nom de fichier).
 *
 * Cas corrigés :
 *  - 155028 : employee_contracts (c < e) avant employees
 *  - 155029 : employee_allowances (e < p) avant payroll_allowance_types
 *  - 155030 : payroll_items (i < r) avant payroll_runs
 *
 * Le bloc try/catch garantit la compatibilité :
 *   - Fresh install  : les FK sont ajoutées normalement
 *   - DB existante   : les FK existent déjà → on ignore silencieusement
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── employee_contracts -> employees ───────────────────────────────────
        // Timestamp 155028 : employee_contracts (c) < employees (s) alpha
        $this->addFkSafe('employee_contracts', function (Blueprint $table) {
            $table->foreign('employee_id')
                  ->references('id')->on('employees')
                  ->cascadeOnDelete();
        });

        // ── employee_allowances -> payroll_allowance_types ────────────────────
        // Timestamp 155029 : employee_allowances (e) < payroll_allowance_types (p) alpha
        $this->addFkSafe('employee_allowances', function (Blueprint $table) {
            $table->foreign('payroll_allowance_type_id')
                  ->references('id')->on('payroll_allowance_types')
                  ->cascadeOnDelete();
        });

        // ── payroll_items -> payroll_runs / employees ─────────────────────────
        // Timestamp 155030 : payroll_items (i) < payroll_runs (r) alpha
        $this->addFkSafe('payroll_items', function (Blueprint $table) {
            $table->foreign('payroll_run_id')
                  ->references('id')->on('payroll_runs')
                  ->cascadeOnDelete();
            $table->foreign('employee_id')
                  ->references('id')->on('employees')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $this->dropFkSafe($table, 'payroll_run_id');
            $this->dropFkSafe($table, 'employee_id');
        });

        Schema::table('employee_allowances', function (Blueprint $table) {
            $this->dropFkSafe($table, 'payroll_allowance_type_id');
        });

        Schema::table('employee_contracts', function (Blueprint $table) {
            $this->dropFkSafe($table, 'employee_id');
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Ajoute une FK seulement si elle n'existe pas déjà. */
    private function addFkSafe(string $table, \Closure $callback): void
    {
        try {
            Schema::table($table, $callback);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate foreign key')) {
                return; // FK déjà présente (DB existante) — on ignore
            }
            throw $e;
        }
    }

    /** Supprime une FK seulement si elle existe. */
    private function dropFkSafe(Blueprint $table, string $column): void
    {
        try {
            $table->dropForeign([$column]);
        } catch (\Throwable) {
            // FK absente — on ignore
        }
    }
};
