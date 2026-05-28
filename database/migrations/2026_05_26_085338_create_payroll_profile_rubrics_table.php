<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table pivot enrichie : lie un profil à ses rubriques avec possibilité de surcharge.
     * Une rubrique peut avoir des valeurs différentes selon le profil
     * (ex : prime cadre = 15%, prime non-cadre = 10%).
     */
    public function up(): void
    {
        Schema::create('payroll_profile_rubrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('payroll_profiles')->cascadeOnDelete();
            $table->foreignId('rubric_id')->constrained('pay_rubrics')->cascadeOnDelete();

            // Active ou désactivée pour ce profil (héritée = true par défaut)
            $table->boolean('is_active')->default(true);

            // Surcharges optionnelles (null = hérité de la rubrique du plan)
            $table->enum('override_calc_type', ['fixe', 'taux', 'formule', 'manuel'])->nullable();
            $table->unsignedBigInteger('override_fixed_amount')->nullable();
            $table->decimal('override_rate', 8, 4)->nullable();
            $table->string('override_formula', 500)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            // Une rubrique n'est ajoutée qu'une fois par profil
            $table->unique(['profile_id', 'rubric_id']);
            $table->index('profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_profile_rubrics');
    }
};
