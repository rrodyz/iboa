<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [P3.B] Retenue « Effort de paix » — spécificité fiscale Burkina Faso.
 *
 * Base légale : Loi des Finances 2016 (Burkina Faso) — 1% du revenu net imposable.
 *   → Ajout de effort_paix_enabled + effort_paix_rate dans payroll_settings
 *   → Ajout de effort_paix_amount dans payroll_items (montant calculé par bulletin)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->boolean('effort_paix_enabled')
                  ->default(true)
                  ->after('iuts_brackets')
                  ->comment('[P3.B] Retenue Effort de paix active pour cette entreprise ?');

            $table->decimal('effort_paix_rate', 5, 2)
                  ->default(1.00)
                  ->after('effort_paix_enabled')
                  ->comment('[P3.B] Taux Effort de paix en % du net imposable (légal BF = 1 %).');
        });

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->integer('effort_paix_amount')
                  ->default(0)
                  ->after('iuts_amount')
                  ->comment('[P3.B] Retenue Effort de paix calculée sur ce bulletin (0 si désactivé).');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn(['effort_paix_enabled', 'effort_paix_rate']);
        });

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn('effort_paix_amount');
        });
    }
};
