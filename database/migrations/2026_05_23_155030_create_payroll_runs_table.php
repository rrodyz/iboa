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
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('period_month'); // 1-12
            $table->unsignedSmallInteger('period_year');
            $table->enum('status', ['brouillon', 'calcule', 'valide', 'paye'])->default('brouillon');

            // Totaux consolidés
            $table->unsignedBigInteger('total_brut')->default(0);
            $table->unsignedBigInteger('total_cnss_employee')->default(0);
            $table->unsignedBigInteger('total_cnss_employer')->default(0);
            $table->unsignedBigInteger('total_iuts')->default(0);
            $table->unsignedBigInteger('total_net')->default(0);
            $table->unsignedTinyInteger('employee_count')->default(0);

            $table->text('notes')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->date('paid_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'period_month', 'period_year'], 'unique_payroll_period');
            $table->index(['company_id', 'period_year', 'period_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
