<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [RH-PRO] Rubriques de paie paramétrables — inspiré Sage Paie.
 *
 * Chaque rubrique représente une ligne du bulletin de salaire :
 * gain (salaire, prime, indemnité), retenue (CNSS, IUTS, avance),
 * cotisation patronale, ou rubrique d'information.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_rubrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // ─── Identification ───────────────────────────────────────────────
            $table->string('code', 30)->comment('Code unique ex: SAL_BASE, CNSS_SAL');
            $table->string('libelle', 150)->comment('Libellé affiché sur le bulletin');
            $table->string('description', 500)->nullable();

            // ─── Type de rubrique ─────────────────────────────────────────────
            $table->enum('type', [
                'gain',            // Salaire, prime, indemnité → GAIN brut
                'retenue',         // CNSS salarié, IUTS, avance, absence
                'cotisation_pat',  // CNSS patronal (hors bulletin salarié)
                'information',     // Information pure, pas de calcul
            ])->default('gain');

            // ─── Méthode de calcul ────────────────────────────────────────────
            $table->enum('calc_type', [
                'fixe',     // Montant fixe (fixed_amount)
                'taux',     // Base × taux %
                'formule',  // Expression PHP évaluée côté service
                'manuel',   // Saisie manuelle dans PayrollVariable
            ])->default('manuel');

            $table->string('base_ref', 50)->nullable()
                  ->comment('Référence pour taux : salaire_base | salaire_brut | cnss_base | imposable');
            $table->decimal('rate', 8, 4)->nullable()->comment('Taux % si calc_type=taux');
            $table->bigInteger('fixed_amount')->nullable()->comment('Montant fixe FCFA');
            $table->text('formula')->nullable()->comment('Formule PHP ex: $base * $rate / 100');

            // ─── Inclusion dans les totaux ────────────────────────────────────
            $table->boolean('is_taxable')->default(true)
                  ->comment('Imposable IUTS ?');
            $table->boolean('is_cnss_base')->default(true)
                  ->comment('Inclus dans la base CNSS ?');
            $table->boolean('is_in_brut')->default(true)
                  ->comment('Inclus dans le salaire brut ?');

            // ─── Affichage ────────────────────────────────────────────────────
            $table->unsignedSmallInteger('display_order')->default(100);
            $table->boolean('show_on_bulletin')->default(true)
                  ->comment('Afficher sur le bulletin individuel ?');
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_rubrics');
    }
};
