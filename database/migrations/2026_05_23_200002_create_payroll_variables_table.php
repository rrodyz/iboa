<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Variables mensuelles de paie (saisie avant calcul).
 * Couvre : heures supplémentaires, absences, primes ponctuelles,
 * avances/déductions. Remplace/complète les rubriques fixes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // Type de rubrique variable
            $table->string('type', 40)->comment(
                'hs_25|hs_50|hs_nuit|absence_cp|absence_maladie|absence_injust|'.
                'prime_exceptionnelle|indemnite_cp|avance_deduction|retenue_autre|gain_autre'
            );
            $table->string('label', 120)->comment('Libellé personnalisé affiché sur le bulletin');

            // Quantité + unité (pour HS ou absences)
            $table->decimal('qty', 8, 2)->default(0)->comment('Nb heures / jours / 1 si forfait');
            $table->string('unit', 20)->default('forfait')->comment('heures|jours|forfait');
            $table->decimal('unit_amount', 12, 4)->nullable()->comment('Taux horaire ou journalier calculé');

            // Montant final (calculé ou saisi)
            $table->integer('amount')->comment('Montant FCFA (positif = gain, négatif = retenue)');

            // Flags de traitement comptable
            $table->boolean('is_gain')->default(true)->comment('true=gain, false=retenue');
            $table->boolean('is_taxable')->default(true)->comment('Entre dans le brut imposable');
            $table->boolean('is_social_charged')->default(true)->comment('Entre dans assiette CNSS');

            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_variables');
    }
};
