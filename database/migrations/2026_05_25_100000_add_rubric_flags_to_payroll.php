<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [P1] Deux ajouts liés aux rubriques de paie :
 *  1. pay_rubrics.is_iuts_base — flag distinct de is_cnss_base pour distinguer
 *     "soumis CNSS" et "soumis IUTS" (spec section 5).
 *  2. employee_allowances.pay_rubric_id — lien optionnel vers la rubrique de paie
 *     qui pilote les flags (is_in_brut, is_cnss_base, is_iuts_base) de cette
 *     allocation. Si NULL → comportement antérieur inchangé.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── 1. Colonne is_iuts_base sur pay_rubrics ─────────────────────────
        Schema::table('pay_rubrics', function (Blueprint $table) {
            $table->boolean('is_iuts_base')
                  ->default(true)
                  ->after('is_cnss_base')
                  ->comment('Soumis IUTS/ITS ? Distinct de is_taxable et is_cnss_base.');
        });

        // ─── 2. Lien pay_rubric_id sur employee_allowances ───────────────────
        Schema::table('employee_allowances', function (Blueprint $table) {
            $table->foreignId('pay_rubric_id')
                  ->nullable()
                  ->after('payroll_allowance_type_id')
                  ->constrained('pay_rubrics')
                  ->nullOnDelete()
                  ->comment('Rubrique paramétrée qui gouverne les flags de calcul.');
        });
    }

    public function down(): void
    {
        Schema::table('employee_allowances', function (Blueprint $table) {
            $table->dropForeign(['pay_rubric_id']);
            $table->dropColumn('pay_rubric_id');
        });

        Schema::table('pay_rubrics', function (Blueprint $table) {
            $table->dropColumn('is_iuts_base');
        });
    }
};
