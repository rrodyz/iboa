<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrichit payroll_items avec le détail des rubriques variables
 * (HS, absences, primes exceptionnelles, avances, retenues)
 * et les cumuls annuels (YTD = Year To Date).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            // ─ Heures supplémentaires ──────────────────────────────────────────
            $table->decimal('hs_25_hours', 6, 2)->default(0)->after('total_allowances_non_taxable');
            $table->integer('hs_25_amount')->default(0)->after('hs_25_hours');
            $table->decimal('hs_50_hours', 6, 2)->default(0)->after('hs_25_amount');
            $table->integer('hs_50_amount')->default(0)->after('hs_50_hours');
            $table->decimal('hs_nuit_hours', 6, 2)->default(0)->after('hs_50_amount');
            $table->integer('hs_nuit_amount')->default(0)->after('hs_nuit_hours');

            // ─ Absences ────────────────────────────────────────────────────────
            $table->decimal('absence_days', 5, 2)->default(0)->after('hs_nuit_amount');
            $table->integer('absence_amount')->default(0)->comment('Déduction brute absences')->after('absence_days');

            // ─ Primes et retenues ponctuelles ─────────────────────────────────
            $table->integer('primes_exceptionnelles')->default(0)->after('absence_amount');
            $table->integer('autres_gains')->default(0)->after('primes_exceptionnelles');
            $table->integer('avances_deductions')->default(0)->after('autres_gains');
            $table->integer('autres_retenues')->default(0)->after('avances_deductions');

            // ─ Coût employeur stocké ───────────────────────────────────────────
            $table->integer('cout_employeur')->default(0)->after('salaire_net');

            // ─ Cumuls annuels (Year To Date) ──────────────────────────────────
            $table->integer('cumul_brut_ytd')->default(0)->after('cout_employeur');
            $table->integer('cumul_cnss_ytd')->default(0)->after('cumul_brut_ytd');
            $table->integer('cumul_iuts_ytd')->default(0)->after('cumul_cnss_ytd');
            $table->integer('cumul_net_ytd')->default(0)->after('cumul_iuts_ytd');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn([
                'hs_25_hours','hs_25_amount','hs_50_hours','hs_50_amount',
                'hs_nuit_hours','hs_nuit_amount',
                'absence_days','absence_amount',
                'primes_exceptionnelles','autres_gains','avances_deductions','autres_retenues',
                'cout_employeur',
                'cumul_brut_ytd','cumul_cnss_ytd','cumul_iuts_ytd','cumul_net_ytd',
            ]);
        });
    }
};
