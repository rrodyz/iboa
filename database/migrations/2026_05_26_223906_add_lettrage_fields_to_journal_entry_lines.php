<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lettrage des comptes de tiers (401, 411…) — SYSCOHADA.
 *
 * La colonne reconciliation_ref existait déjà mais était inutilisée.
 * On lui donne un rôle officiel : code de lettre (A, B, C… AA, AB…).
 * On ajoute le tracking (qui, quand) pour l'audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            // reconciliation_ref existait déjà (nullable string) — devient le code lettre
            // On ajoute un index pour accélérer les recherches par lettre
            $table->index(['account_id', 'reconciliation_ref'], 'idx_jel_account_lettre');

            // Tracking du lettrage
            $table->timestamp('lettered_at')->nullable()->after('reconciliation_ref');
            $table->foreignId('lettered_by')->nullable()->after('lettered_at')
                  ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->dropIndex('idx_jel_account_lettre');
            $table->dropConstrainedForeignId('lettered_by');
            $table->dropColumn(['lettered_at']);
        });
    }
};
