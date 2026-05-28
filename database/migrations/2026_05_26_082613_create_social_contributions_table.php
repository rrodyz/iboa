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
        Schema::create('social_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // ─── Organisme ────────────────────────────────────────────────────
            $table->string('code', 20)->comment('Ex: CNSS, AT, RETRAITE, ASSURANCE');
            $table->string('libelle', 150)->comment('Ex: CNSS — Part salariale');
            $table->enum('organisme', ['cnss', 'assurance', 'retraite', 'mutuelle', 'autre'])
                ->default('cnss');

            // ─── Taux ─────────────────────────────────────────────────────────
            $table->decimal('taux_salarie', 7, 4)->default(0)
                ->comment('Taux part salarié (%)');
            $table->decimal('taux_employeur', 7, 4)->default(0)
                ->comment('Taux part patronale (%)');

            // ─── Base cotisable ───────────────────────────────────────────────
            $table->enum('base_cotisable', ['salaire_brut', 'salaire_base', 'plafonne', 'custom'])
                ->default('salaire_brut')
                ->comment('Base sur laquelle s\'appliquent les taux');
            $table->unsignedBigInteger('plafond')->nullable()
                ->comment('Plafond mensuel FCFA si base = plafonne');
            $table->string('base_ref', 50)->nullable()
                ->comment('Référence custom si base = custom');

            // ─── Comptes comptables ───────────────────────────────────────────
            $table->string('account_salarie', 20)->nullable()
                ->comment('Compte CNSS/cotisation salarié ex: 431000');
            $table->string('account_employeur', 20)->nullable()
                ->comment('Compte CNSS/cotisation patronal ex: 431100');

            // ─── Validité ─────────────────────────────────────────────────────
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'organisme', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_contributions');
    }
};
