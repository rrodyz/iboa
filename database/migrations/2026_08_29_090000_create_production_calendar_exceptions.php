<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [PROD-01 — Phase 6] Jours fériés / fermetures déclarés — le calendrier de
 * capacité (CapacityCalendarService) les exclut de l'horizon, en plus des
 * week-ends (samedi/dimanche, non stockés : logique jour-de-semaine).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_calendar_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('label', 150)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_calendar_exceptions');
    }
};
