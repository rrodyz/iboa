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
        Schema::create('payroll_constants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // ─── Identification ───────────────────────────────────────────────
            $table->string('code', 30)->comment('Ex: SMIG, CNSS_SAL, HS_25, NB_HEURES');
            $table->string('libelle', 150)->comment('Ex: Salaire minimum interprofessionnel');
            $table->text('description')->nullable();

            // ─── Valeur typée ─────────────────────────────────────────────────
            $table->enum('value_type', ['montant', 'taux', 'nombre', 'texte', 'booleen'])
                ->default('montant')
                ->comment('Type de valeur pour validation et affichage');
            $table->string('value_raw', 500)
                ->comment('Valeur stockée en string — castée selon value_type');
            $table->string('unit', 20)->nullable()
                ->comment('Unité d\'affichage ex: FCFA, %, heures, jours');

            // ─── Validité temporelle (historisation) ──────────────────────────
            $table->date('valid_from')->nullable()
                ->comment('Date à partir de laquelle la constante est active');
            $table->date('valid_until')->nullable()
                ->comment('Date de fin (null = valable indéfiniment)');
            $table->boolean('is_active')->default(true);

            // ─── Groupement ──────────────────────────────────────────────────
            $table->enum('groupe', [
                'cnss', 'iuts', 'heures', 'conges', 'smig',
                'anciennete', 'fiscal', 'autre',
            ])->default('autre')->comment('Groupe pour affichage dans l\'UI');

            // ─── Audit ────────────────────────────────────────────────────────
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Un code peut avoir plusieurs lignes (historique par date)
            $table->index(['company_id', 'code', 'valid_from']);
            $table->index(['company_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_constants');
    }
};
