<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [NO-HARDCODE] Ancienneté BF — Art. 109 Code du Travail.
 * Les taux ne sont plus en dur dans PayrollService.
 * On les stocke ici pour chaque entreprise, modifiables via l'UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            // Taux d'ancienneté par année complète de service (%)
            $table->decimal('anc_rate_per_year', 5, 2)->nullable()->after('hs_rate_nuit');
            // Plafond du taux d'ancienneté cumulé (%)
            $table->decimal('anc_rate_max_pct',  5, 2)->nullable()->after('anc_rate_per_year');
        });

        // Initialiser les lignes existantes avec les taux BF actuels
        DB::table('payroll_settings')->whereNull('anc_rate_per_year')->update([
            'anc_rate_per_year' => 2.00,
            'anc_rate_max_pct'  => 25.00,
        ]);
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn(['anc_rate_per_year', 'anc_rate_max_pct']);
        });
    }
};
