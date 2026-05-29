<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration déférée RH — résout les conflits d'ordre dans les groupes
 * à timestamps identiques (employee_contracts vs employees, payroll_items vs payroll_runs).
 *
 * Ces FK ne peuvent pas être déclarées inline car les tables parentes sont
 * créées dans le même groupe de timestamp, après la table enfant (ordre alphabétique).
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
        // Même timestamp 155028 : create_employee_contracts < create_employees (alpha)
        $this->addFkSafe('employee_contracts', function (Blueprint $table) {
            $table->foreign('employee_id')
                  ->references('id')->on('employees')
                  ->cascadeOnDelete();
        });

        // ── payroll_items -> payroll_runs / employees ─────────────────────────
        // Même timestamp 155030 : create_payroll_items < create_payroll_runs (alpha)
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
            // 1826 = Duplicate foreign key constraint name (MySQL)
            // 1005 = Can't create table (MySQL, parfois levé pour FK dupliquée)
            if (in_array($e->getCode(), ['HY000']) && str_contains($e->getMessage(), 'Duplicate foreign key')) {
                // FK déjà présente (DB existante) — on ignore
                return;
            }
            throw $e;
        }
    }

    /** Supprime une FK seulement si elle existe. */
    private function dropFkSafe(Blueprint $table, string $column): void
    {
        try {
            $table->dropForeign([$column]);
        } catch (\Throwable $e) {
            // FK absente — on ignore
        }
    }
};
