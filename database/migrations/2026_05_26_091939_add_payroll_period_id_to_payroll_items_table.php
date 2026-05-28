<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rattache chaque bulletin à sa période de paie.
     *
     * Nullable intentionnellement :
     *   - Les bulletins existants (avant Phase 4) ont period_id = null.
     *   - Un null est interprété comme "période ouverte" dans le guard de service.
     *   - Aucune donnée existante n'est perdue ou modifiée.
     */
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->foreignId('payroll_period_id')
                  ->nullable()
                  ->after('template_id')
                  ->constrained('payroll_periods')
                  ->nullOnDelete();

            $table->index('payroll_period_id');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropForeign(['payroll_period_id']);
            $table->dropIndex(['payroll_period_id']);
            $table->dropColumn('payroll_period_id');
        });
    }
};
