<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [RH-PRO] Prêts salariés — différents des avances ponctuelles.
 *
 * Un prêt est remboursé sur plusieurs mois via déductions mensuelles.
 * Chaque remboursement mensuel est enregistré dans employee_loan_payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->string('loan_number', 30)->unique()
                  ->comment('Numéro de prêt ex: PRET-2026-001');
            $table->unsignedBigInteger('amount')->comment('Montant total accordé (FCFA)');
            $table->unsignedBigInteger('monthly_deduction')->comment('Mensualité de remboursement (FCFA)');
            $table->unsignedBigInteger('remaining_balance')->comment('Solde restant dû');
            $table->unsignedSmallInteger('nb_months')->default(0)
                  ->comment('Nombre de mensualités prévues');

            $table->enum('status', ['actif', 'rembourse', 'annule'])->default('actif');
            $table->date('start_date')->comment('Date de début du remboursement');
            $table->date('end_date')->nullable()->comment('Date de fin théorique');
            $table->text('notes')->nullable();
            $table->text('reason')->nullable()->comment('Motif du prêt');

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'employee_id', 'status']);
        });

        Schema::create('employee_loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('period_month');
            $table->unsignedSmallInteger('period_year');
            $table->unsignedBigInteger('amount')->comment('Montant remboursé ce mois');
            $table->unsignedBigInteger('balance_after')->comment('Solde restant après remboursement');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_loan_id', 'period_year', 'period_month'], 'idx_loan_payments_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_loan_payments');
        Schema::dropIfExists('employee_loans');
    }
};
