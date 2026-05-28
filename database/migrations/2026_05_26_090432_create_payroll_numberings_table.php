<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Règles de numérotation des bulletins de paie.
     * Exemple de format généré : BUL-2026-05-0001
     */
    public function up(): void
    {
        Schema::create('payroll_numberings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('code', 30);
            $table->string('libelle', 150);

            // Composants du format
            $table->string('prefix', 20)->default('BUL');
            $table->string('separator', 5)->default('-');
            $table->enum('year_format', ['YYYY', 'YY', 'none'])->default('YYYY');
            $table->enum('month_format', ['MM', 'M', 'none'])->default('MM');
            $table->tinyInteger('seq_length')->unsigned()->default(4)->comment('Nb chiffres ex: 4 → 0001');

            // Réinitialisation de la séquence
            $table->enum('reset_on', ['year', 'month', 'never'])->default('year');

            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
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
        Schema::dropIfExists('payroll_numberings');
    }
};
