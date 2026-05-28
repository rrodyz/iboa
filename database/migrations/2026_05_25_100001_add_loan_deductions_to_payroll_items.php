<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [P1] Colonne loan_deductions sur payroll_items.
 *
 * Stocke le total des remboursements de prêts déduits automatiquement
 * lors du calcul de paie (distinct de avances_deductions et autres_retenues).
 * Permet d'afficher une ligne dédiée sur le bulletin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->integer('loan_deductions')
                  ->default(0)
                  ->after('avances_deductions')
                  ->comment('Remboursements de prêts déduits automatiquement sur ce bulletin.');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn('loan_deductions');
        });
    }
};
