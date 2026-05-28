<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modèles de présentation des bulletins de paie PDF.
     * Contrôle les éléments affichés et la mise en page.
     */
    public function up(): void
    {
        Schema::create('payroll_bulletin_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('code', 30);
            $table->string('libelle', 150);
            $table->text('description')->nullable();

            // En-tête / pied de page
            $table->text('header_text')->nullable()->comment('Texte libre affiché sous le nom de la société');
            $table->text('footer_text')->nullable()->comment('Texte de bas de page : mentions légales, confidentialité…');

            // Options d'affichage
            $table->boolean('show_logo')->default(true);
            $table->boolean('show_company_address')->default(true);
            $table->boolean('show_employee_photo')->default(false);
            $table->boolean('show_net_a_payer_box')->default(true);
            $table->boolean('show_cumuls')->default(true);
            $table->boolean('show_conges_solde')->default(true);
            $table->boolean('show_cout_employeur')->default(false);

            // Mise en page
            $table->enum('paper_size', ['A4', 'letter'])->default('A4');
            $table->enum('orientation', ['portrait', 'landscape'])->default('portrait');
            $table->enum('primary_color', [
                'indigo', 'blue', 'gray', 'green', 'red', 'orange', 'teal'
            ])->default('indigo')->comment('Couleur accent du bulletin');

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_bulletin_templates');
    }
};
