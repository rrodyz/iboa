<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [COMPTA-PRO-05] Verrouillage par période mensuelle.
 *
 * Chaque ligne fige un mois donné : tant que la ligne existe, aucune écriture
 * de ce mois ne peut être validée, modifiée ou supprimée. Plus granulaire que
 * le verrouillage d'exercice, utile pour les arrêtés mensuels après revue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_period_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');     // 2026
            $table->unsignedTinyInteger('month');     // 1-12
            $table->timestamp('locked_at');
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            // Un seul lock par (company, année, mois)
            $table->unique(['company_id', 'year', 'month']);
            $table->index(['company_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_period_locks');
    }
};
