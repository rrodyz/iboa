<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->unsignedInteger('smig')->default(45000)->after('cnss_ceiling')
                  ->comment('Salaire Minimum Interprofessionnel Garanti (FCFA/mois)');
            $table->unsignedSmallInteger('leave_days_year')->default(30)->after('work_hours_day')
                  ->comment('Congés annuels légaux (jours ouvrables)');
        });

        // Hydrate les lignes existantes
        DB::table('payroll_settings')->update([
            'smig'            => 45000,
            'leave_days_year' => 30,
        ]);
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn(['smig', 'leave_days_year']);
        });
    }
};
