<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', [
                'present',   // Présent
                'absent',    // Absent injustifié
                'conge',     // En congé validé
                'maladie',   // Congé maladie
                'mission',   // En mission
                'ferie',     // Jour férié
                'weekend',   // Week-end
                'demi_j',    // Demi-journée
            ])->default('present');
            $table->time('arrival_time')->nullable();
            $table->time('departure_time')->nullable();
            $table->decimal('worked_hours', 4, 2)->nullable();
            $table->decimal('overtime_hours', 4, 2)->nullable()->default(0);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Un employé ne peut avoir qu'un seul enregistrement par date
            $table->unique(['company_id', 'employee_id', 'date']);
            $table->index(['company_id', 'date']);
            $table->index(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
