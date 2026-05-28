<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan d'amortissement : une ligne par année par immobilisation.
 *
 * is_posted = true → dotation comptabilisée dans le journal GL.
 * Une fois postée, la ligne est figée (le montant ne peut plus être recalculé).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            $table->unsignedSmallInteger('fiscal_year');       // 2026, 2027, …
            $table->decimal('depreciation_amount', 15, 0);    // dotation de l'exercice
            $table->decimal('cumulated_depreciation', 15, 0); // cumul fin de période
            $table->decimal('net_book_value', 15, 0);         // VNC = coût − cumul

            // Écriture GL générée (DR 681x / CR 28xx)
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->boolean('is_posted')->default(false);      // true = figée, imodifiable

            $table->timestamps();

            $table->unique(['fixed_asset_id', 'fiscal_year']); // une ligne par an par actif
            $table->index(['company_id', 'fiscal_year', 'is_posted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_depreciations');
    }
};
