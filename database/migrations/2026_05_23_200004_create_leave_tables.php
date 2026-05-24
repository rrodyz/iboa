<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Congés & Absences.
 * Tables : leave_types, leave_requests, leave_balances.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Types de congés ─────────────────────────────────────────────────────
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20);
            $table->decimal('days_per_year', 5, 1)->default(0)
                  ->comment('Jours de droit par an (0 = illimité / à la demande)');
            $table->boolean('is_paid')->default(true)->comment('Congé payé ou non');
            $table->boolean('deduct_from_salary')->default(false)
                  ->comment('Déduire du salaire si is_paid=false');
            $table->string('color', 20)->default('blue')
                  ->comment('Couleur pour le calendrier (tailwind)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        // ── Demandes / soldes ────────────────────────────────────────────────────
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 5, 1)->comment('Jours calculés (hors weekend si applicable)');
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('en_attente')
                  ->comment('en_attente|approuve|refuse|annule');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable()->comment('Note du responsable RH');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['leave_type_id', 'start_date']);
        });

        // ── Soldes de congés ─────────────────────────────────────────────────────
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('entitled_days', 6, 1)->default(0)->comment('Jours accordés');
            $table->decimal('taken_days', 6, 1)->default(0)->comment('Jours pris');
            $table->decimal('remaining_days', 6, 1)->virtualAs('entitled_days - taken_days');
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year'], 'uq_leave_balance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
    }
};
