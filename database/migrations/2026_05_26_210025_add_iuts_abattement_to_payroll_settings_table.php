<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            // [P3.C] Abattement forfaitaire IUTS — BF CGI Art. 130
            // Déduction pour frais professionnels avant application du barème.
            // Valeur légale Burkina Faso : 20 %.
            $table->decimal('iuts_abattement_rate', 5, 2)
                  ->default(20.00)
                  ->after('iuts_brackets')
                  ->comment('Abattement forfaitaire frais professionnels IUTS (%) — CGI Art. 130');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn('iuts_abattement_rate');
        });
    }
};
