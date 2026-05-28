<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immobilisations (actifs fixes) — SYSCOHADA plan comptable.
 *
 * Les taux d'amortissement sont TOUJOURS stockés sur l'actif (durée_vie, méthode).
 * Jamais codés en dur. Les lignes de dotation postées sont figées (is_posted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            $table->string('code', 30);                     // IMB-2026-001 (unique par company)
            $table->string('name');
            $table->text('description')->nullable();

            $table->enum('category', [
                'materiel_informatique',
                'vehicule',
                'mobilier_bureau',
                'materiel_industriel',
                'batiment',
                'terrain',
                'logiciel',
                'autre',
            ])->default('autre');

            $table->date('acquisition_date');               // date d'achat
            $table->date('commissioning_date');             // date de mise en service (prorata 1ère année)

            $table->decimal('acquisition_cost', 15, 0);    // valeur d'origine (FCFA entiers)
            $table->decimal('residual_value',   15, 0)->default(0); // valeur résiduelle estimée

            // Amortissement — stocké sur l'actif, jamais codé en dur
            $table->unsignedTinyInteger('useful_life_years')->default(5);
            $table->enum('depreciation_method', ['lineaire', 'degressif'])->default('lineaire');

            // Comptes GL (surchargeables par l'utilisateur au lieu des défauts par catégorie)
            $table->string('asset_account',  10);           // ex: 2454 (immo corpo)
            $table->string('depr_account',   10);           // ex: 28454 (amortissement cumulé)
            $table->string('charge_account', 10);           // ex: 6813 (dotation aux amortissements)

            $table->enum('status', ['en_service', 'cede', 'mis_au_rebut'])->default('en_service');
            $table->date('cession_date')->nullable();
            $table->decimal('cession_value', 15, 0)->nullable();

            $table->string('vendor', 255)->nullable();
            $table->string('invoice_ref', 100)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
