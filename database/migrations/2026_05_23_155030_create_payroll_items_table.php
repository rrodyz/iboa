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
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // Éléments de calcul
            $table->unsignedBigInteger('base_salary')->default(0);
            $table->unsignedBigInteger('total_allowances_taxable')->default(0);
            $table->unsignedBigInteger('total_allowances_non_taxable')->default(0);

            // Résultats
            $table->unsignedBigInteger('salaire_brut')->default(0);
            $table->unsignedBigInteger('cnss_base')->default(0)->comment('Base plafonnée CNSS');
            $table->unsignedBigInteger('cnss_employee')->default(0)->comment('Cotisation employé 5,5%');
            $table->unsignedBigInteger('cnss_employer')->default(0)->comment('Cotisation patronale 16%');
            $table->unsignedBigInteger('salaire_imposable')->default(0)->comment('Brut - CNSS employé');
            $table->decimal('nb_parts', 4, 1)->default(1.0)->comment('Parts fiscales IUTS');
            $table->unsignedBigInteger('iuts_amount')->default(0)->comment('IUTS dû');
            $table->unsignedBigInteger('salaire_net')->default(0)->comment('Net à payer');

            // Présence
            $table->unsignedTinyInteger('worked_days')->default(26);
            $table->unsignedTinyInteger('total_days')->default(26);

            // Snapshot employé au moment du calcul
            $table->string('employee_name', 150)->default('');
            $table->string('employee_matricule', 30)->default('');
            $table->string('job_title', 100)->nullable();
            $table->string('department_name', 100)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id'], 'unique_payroll_item');
            $table->index('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
