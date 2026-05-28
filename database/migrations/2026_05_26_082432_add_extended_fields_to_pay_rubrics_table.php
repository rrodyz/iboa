<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Enrichissement des rubriques de paie.
 * Migration ADDITIVE uniquement — aucune colonne existante modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay_rubrics', function (Blueprint $table) {

            // Lien plan de paie (nullable, table créée en Phase 2)
            $table->unsignedBigInteger('plan_id')
                ->nullable()->after('company_id')
                ->comment('Plan de paie (FK vers payroll_plans — Phase 2)');

            // Catégorie (nature de rubrique — Sage)
            $table->enum('categorie', [
                'salaire', 'prime', 'indemnite', 'absence',
                'avance', 'pret', 'impot', 'cnss', 'avantage', 'autre',
            ])->default('salaire')->after('type')
              ->comment('Nature de la rubrique');

            // Sens : addition ou déduction
            $table->enum('sens', ['addition', 'deduction'])
                ->default('addition')->after('categorie')
                ->comment('addition = augmente le net | deduction = diminue le net');

            // Plafond mensuel applicable
            $table->unsignedBigInteger('plafond')
                ->nullable()->after('formula')
                ->comment('Plafond mensuel FCFA (null = pas de plafond)');

            // Mode d'arrondi
            $table->enum('arrondi', ['aucun', 'superieur', 'inferieur', 'bancaire'])
                ->default('aucun')->after('plafond')
                ->comment('Mode d\'arrondi du montant calculé');

            // Compte comptable associé
            $table->string('account_code', 20)
                ->nullable()->after('arrondi')
                ->comment('Numéro de compte comptable (ex: 641000)');

            // Inclus dans le net (distinct de is_in_brut)
            $table->boolean('is_in_net')
                ->default(true)->after('is_in_brut')
                ->comment('Inclus dans le salaire net à payer ?');

            // Soumis aux charges patronales
            $table->boolean('is_employer_charged')
                ->default(false)->after('is_in_net')
                ->comment('Soumis aux charges patronales CNSS/AT ?');

            // Dates de validité
            $table->date('valid_from')
                ->nullable()->after('is_active')
                ->comment('Date de début de validité');

            $table->date('valid_until')
                ->nullable()->after('valid_from')
                ->comment('Date de fin de validité (null = sans limite)');

            // Notes internes
            $table->text('notes')
                ->nullable()->after('valid_until')
                ->comment('Commentaire interne non affiché sur bulletin');
        });
    }

    public function down(): void
    {
        Schema::table('pay_rubrics', function (Blueprint $table) {
            $table->dropColumn([
                'plan_id', 'categorie', 'sens',
                'plafond', 'arrondi', 'account_code',
                'is_in_net', 'is_employer_charged',
                'valid_from', 'valid_until', 'notes',
            ]);
        });
    }
};
