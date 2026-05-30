<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REPAIR MIGRATION
 *
 * La migration d'origine 2026_05_11_131836_add_tax_rate_id_to_clients_table
 * est marquée "Ran" dans la table migrations mais la colonne n'existe PAS
 * dans la table clients (bug silencieux — probablement une contrainte FK
 * non résolue au moment de l'exécution initiale).
 *
 * Cette migration répare l'écart de façon idempotente : ajoute tax_rate_id
 * si absent, sans toucher aux données existantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'tax_rate_id')) {
                $table->unsignedBigInteger('tax_rate_id')
                      ->nullable()
                      ->after('tax_division')
                      ->comment('Taux de TVA par défaut appliqué aux factures de ce client');

                $table->foreign('tax_rate_id')
                      ->references('id')
                      ->on('tax_rates')
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'tax_rate_id')) {
                $table->dropForeign(['tax_rate_id']);
                $table->dropColumn('tax_rate_id');
            }
        });
    }
};
