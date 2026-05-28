<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suivi du dernier numéro de séquence par période.
     * period_key = '2026' (reset_on=year) ou '2026-05' (reset_on=month) ou 'global' (never).
     * Ligne lockée par SELECT FOR UPDATE avant chaque incrément → zéro doublon.
     */
    public function up(): void
    {
        Schema::create('payroll_numbering_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('numbering_id')
                  ->constrained('payroll_numberings')
                  ->cascadeOnDelete();

            $table->string('period_key', 20)->comment('Ex: 2026, 2026-05, global');
            $table->unsignedInteger('last_seq')->default(0);
            $table->timestamps();

            $table->unique(['numbering_id', 'period_key']);
            $table->index('numbering_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_numbering_sequences');
    }
};
