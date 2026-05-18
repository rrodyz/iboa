<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            $table->string('number', 30)->unique();
            $table->string('period_label', 20);   // e.g. "2026-04", "T1-2026"
            $table->enum('period_type', ['mensuel', 'trimestriel'])->default('mensuel');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('declaration_date');
            $table->date('due_date')->nullable();

            $table->enum('status', ['brouillon', 'soumis', 'paye'])->default('brouillon');

            // Montants TVA
            $table->decimal('tva_collectee',    15, 0)->default(0);
            $table->decimal('tva_deductible',   15, 0)->default(0);
            $table->decimal('tva_due',          15, 0)->default(0);   // collectée - déductible
            $table->decimal('credit_tva',       15, 0)->default(0);   // si déductible > collectée
            $table->decimal('amount_paid',      15, 0)->default(0);

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_declarations');
    }
};
