<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [RH-PRO] Paramétrage paie.
 * Une seule ligne par company_id (singleton).
 * Rend taux CNSS, plafonds et barème IUTS modifiables sans toucher au code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // ─── CNSS ──────────────────────────────────────────────────────────
            $table->decimal('cnss_employee_rate', 5, 2)->default(5.50)
                  ->comment('Taux CNSS salarié (%)');
            $table->decimal('cnss_employer_rate', 5, 2)->default(16.00)
                  ->comment('Taux CNSS patronal (%)');
            $table->unsignedBigInteger('cnss_ceiling')->default(650000)
                  ->comment('Plafond mensuel CNSS (FCFA)');
            $table->decimal('cnss_at_rate', 5, 2)->default(3.50)
                  ->comment('Taux AT/MP patronal (%)');

            // ─── Heures supplémentaires ────────────────────────────────────────
            $table->unsignedTinyInteger('work_days_month')->default(26);
            $table->unsignedTinyInteger('work_hours_day')->default(8);
            $table->decimal('hs_rate_25', 5, 2)->default(25.00)
                  ->comment('Majoration HS normales (%)');
            $table->decimal('hs_rate_50', 5, 2)->default(50.00)
                  ->comment('Majoration HS weekend/JF (%)');
            $table->decimal('hs_rate_nuit', 5, 2)->default(75.00)
                  ->comment('Majoration HS nuit (%)');

            // ─── IUTS / Quotient familial ──────────────────────────────────────
            $table->unsignedTinyInteger('nb_parts_max')->default(5);
            $table->decimal('parts_per_child', 3, 1)->default(0.5)
                  ->comment('Parts supplémentaires par enfant');
            $table->json('iuts_brackets')
                  ->comment('Barème IUTS mensuel par part : [[plafond, taux%], ...]');

            // ─── Divers ───────────────────────────────────────────────────────
            $table->string('bulletin_prefix', 10)->default('BUL');
            $table->string('currency_code', 5)->default('FCFA');
            $table->string('country_code', 5)->default('BF');
            $table->text('notes')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_settings');
    }
};
