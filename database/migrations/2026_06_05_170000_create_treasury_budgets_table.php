<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [TRESO] Budgets de trésorerie — prévisionnel d'entrées/sorties par mois,
 * confronté au réalisé (encaissements/décaissements réels).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
            $table->string('name', 150);
            $table->unsignedSmallInteger('year');
            $table->enum('status', ['brouillon', 'valide', 'archive'])->default('brouillon');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->nullOnDelete()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'year', 'name']);
        });

        Schema::create('treasury_budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treasury_budget_id')->constrained('treasury_budgets')->cascadeOnDelete();
            $table->string('category', 100);                    // ex. Ventes, Salaires, Loyer…
            $table->enum('direction', ['entree', 'sortie']);
            $table->unsignedTinyInteger('month');               // 1-12
            $table->decimal('planned_amount', 15, 0)->default(0);
            $table->timestamps();

            $table->index(['treasury_budget_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_budget_lines');
        Schema::dropIfExists('treasury_budgets');
    }
};
