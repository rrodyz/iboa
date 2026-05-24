<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avances sur salaire (acomptes).
 * Une avance approuvée est automatiquement déduite lors du prochain calcul de paie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->integer('amount')->comment('Montant de l\'avance en FCFA');
            $table->date('advance_date');
            $table->string('reason', 255)->nullable();

            $table->string('status', 20)->default('en_attente')
                  ->comment('en_attente|approuve|rembourse|annule');

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // Lié au bulletin où l'avance a été récupérée
            $table->foreignId('recovered_in_run_id')->nullable()
                  ->constrained('payroll_runs')->nullOnDelete();
            $table->date('recovered_at')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_advances');
    }
};
