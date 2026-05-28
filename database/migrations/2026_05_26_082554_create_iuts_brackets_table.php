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
        Schema::create('iuts_brackets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // ─── Localisation ─────────────────────────────────────────────────
            $table->string('pays', 100)->default('Burkina Faso');
            $table->string('country_code', 5)->default('BF');
            $table->enum('impot', ['iuts', 'its', 'autre'])->default('iuts')
                ->comment('Type d\'impôt concerné');

            // ─── Tranche ──────────────────────────────────────────────────────
            $table->unsignedBigInteger('tranche_min')->default(0)
                ->comment('Revenu mensuel minimum (FCFA) — inclus');
            $table->unsignedBigInteger('tranche_max')
                ->comment('Revenu mensuel maximum (FCFA) — inclus. 9999999999 = infini');
            $table->decimal('taux', 6, 3)->default(0)
                ->comment('Taux applicable à la tranche (%)');
            $table->bigInteger('montant_fixe')->default(0)
                ->comment('Montant fixe éventuel ajouté à la tranche (FCFA)');
            $table->decimal('abattement', 6, 3)->default(0)
                ->comment('Abattement applicable (%)');

            // ─── Quotient familial ────────────────────────────────────────────
            $table->unsignedTinyInteger('nb_parts_min')->default(1)
                ->comment('Nombre de parts minimum pour cette ligne (1 = célibataire)');
            $table->unsignedTinyInteger('nb_parts_max')->nullable()
                ->comment('Nombre de parts maximum (null = toutes)');

            // ─── Affichage & tri ──────────────────────────────────────────────
            $table->unsignedSmallInteger('ordre')->default(10)
                ->comment('Ordre d\'affichage dans le barème');

            // ─── Validité ─────────────────────────────────────────────────────
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'country_code', 'impot', 'is_active']);
            $table->index(['company_id', 'tranche_min', 'tranche_max']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iuts_brackets');
    }
};
