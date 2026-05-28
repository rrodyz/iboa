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
        Schema::create('payroll_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // ─── Identification ───────────────────────────────────────────────
            $table->string('code', 20)->comment('Code unique ex: PL-BF-CDI');
            $table->string('libelle', 150)->comment('Libellé ex: Plan CDI Burkina Faso');
            $table->text('description')->nullable();

            // ─── Localisation ─────────────────────────────────────────────────
            $table->string('pays', 100)->default('Burkina Faso');
            $table->string('country_code', 5)->default('BF');
            $table->string('devise', 10)->default('FCFA');

            // ─── Validité ─────────────────────────────────────────────────────
            $table->date('valid_from')->nullable()->comment('Date de début');
            $table->date('valid_until')->nullable()->comment('Date de fin (null = illimité)');
            $table->boolean('is_active')->default(true);

            // ─── Options ──────────────────────────────────────────────────────
            $table->boolean('is_default')->default(false)
                ->comment('Plan appliqué par défaut à tout nouvel employé');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_plans');
    }
};
