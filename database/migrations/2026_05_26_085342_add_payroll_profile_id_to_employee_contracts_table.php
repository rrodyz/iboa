<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Additive only — colonne nullable, aucun existant affecté.
     */
    public function up(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->foreignId('payroll_profile_id')
                  ->nullable()
                  ->after('base_salary')
                  ->constrained('payroll_profiles')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->dropForeign(['payroll_profile_id']);
            $table->dropColumn('payroll_profile_id');
        });
    }
};
