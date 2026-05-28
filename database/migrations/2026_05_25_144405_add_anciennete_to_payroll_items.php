<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les colonnes ancienneté automatique sur payroll_items.
 * anc_rate   : taux en % (ex. 6 pour 6 %)
 * anc_amount : montant calculé en FCFA (inclus dans salaire_brut)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('anc_rate')->default(0)->after('base_salary')
                  ->comment('Taux ancienneté % (2%/an BF, max 25%)');
            $table->unsignedInteger('anc_amount')->default(0)->after('anc_rate')
                  ->comment('Montant ancienneté FCFA (inclus dans salaire_brut)');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['anc_rate', 'anc_amount']);
        });
    }
};
