<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table des périodes de paie.
     *
     * Une période correspond à un mois civil (ex : Mai 2026).
     * Transitions de statut autorisées :
     *   open → closed → locked → archived
     *   locked → open  (déverrouillage admin, traçé)
     *   closed → open  (réouverture)
     *
     * Invariant de sécurité : aucune écriture sur payroll_items
     * n'est autorisée si la période est locked ou archived.
     */
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Code machine unique par société : '2026-05'
            $table->string('code', 7);                  // YYYY-MM
            $table->string('libelle', 100);              // 'Mai 2026'

            $table->date('period_start');                // 2026-05-01
            $table->date('period_end');                  // 2026-05-31

            // ── Statut ──────────────────────────────────────────────────────────
            $table->enum('status', ['open', 'closed', 'locked', 'archived'])
                  ->default('open');

            // ── Clôture ──────────────────────────────────────────────────────────
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // ── Verrouillage définitif ───────────────────────────────────────────
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // ── Déverrouillage (admin — traçé) ──────────────────────────────────
            $table->timestamp('unlocked_at')->nullable();
            $table->foreignId('unlocked_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->text('unlock_reason')->nullable();   // justification obligatoire

            // ── Archivage ────────────────────────────────────────────────────────
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // ── Liaison optionnelle avec le run de paie de la période ───────────
            $table->foreignId('payroll_run_id')
                  ->nullable()
                  ->constrained('payroll_runs')
                  ->nullOnDelete();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Contraintes
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
