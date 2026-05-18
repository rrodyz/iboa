<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Professionnalisation du système de numérotation (style Sage GesCom).
 *
 * Ajoute :
 *  1) Mode de numérotation (auto / manuel) par séquence
 *  2) Champs d'audit (verrou, dernière modif user, raison)
 *  3) Table d'historique complet `document_sequence_audits`
 *
 * Sécurité :
 *  - Aucune donnée existante détruite.
 *  - Tous les nouveaux champs ont une valeur par défaut sûre.
 *  - Les anciens documents et leurs références sont préservés.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────────────────────────────
        // 1) Enrichir document_sequences
        // ─────────────────────────────────────────────────────────────────────
        Schema::table('document_sequences', function (Blueprint $table) {
            if (!Schema::hasColumn('document_sequences', 'numbering_mode')) {
                $table->enum('numbering_mode', ['auto', 'manual'])
                      ->default('auto')
                      ->after('last_number')
                      ->comment('auto = incrément. automatique ; manual = saisie utilisateur (suggérée mais éditable)');
            }
            if (!Schema::hasColumn('document_sequences', 'is_locked')) {
                $table->boolean('is_locked')
                      ->default(false)
                      ->after('numbering_mode')
                      ->comment('Si true, le format (préfixe/padding/année) est verrouillé — seul le compteur peut bouger');
            }
            if (!Schema::hasColumn('document_sequences', 'last_modified_by')) {
                $table->foreignId('last_modified_by')
                      ->nullable()
                      ->after('is_locked')
                      ->constrained('users')
                      ->nullOnDelete();
            }
            if (!Schema::hasColumn('document_sequences', 'last_modified_reason')) {
                $table->string('last_modified_reason', 255)
                      ->nullable()
                      ->after('last_modified_by');
            }
        });

        // ─────────────────────────────────────────────────────────────────────
        // 2) Table d'audit complet
        // ─────────────────────────────────────────────────────────────────────
        if (!Schema::hasTable('document_sequence_audits')) {
            Schema::create('document_sequence_audits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_sequence_id')
                      ->constrained('document_sequences')
                      ->cascadeOnDelete();
                $table->foreignId('user_id')
                      ->nullable()
                      ->constrained('users')
                      ->nullOnDelete();

                $table->enum('action', [
                    'create',         // séquence créée
                    'update_format',  // préfixe / suffixe / padding / année
                    'set_counter',    // compteur défini manuellement
                    'reset_counter',  // compteur remis à 0
                    'lock',           // verrouillage
                    'unlock',         // déverrouillage
                    'next_number',    // n° généré automatiquement (optionnel — audit léger)
                ]);

                // Snapshot avant / après (JSON)
                $table->json('before')->nullable();
                $table->json('after')->nullable();

                $table->string('reason', 255)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();

                $table->timestamp('created_at')->useCurrent();

                $table->index(['document_sequence_id', 'created_at'], 'dsa_seq_date_idx');
                $table->index(['user_id', 'created_at'],              'dsa_user_date_idx');
                $table->index(['action', 'created_at'],               'dsa_action_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequence_audits');

        Schema::table('document_sequences', function (Blueprint $table) {
            // dropForeign en premier, sinon dropColumn échoue
            if (Schema::hasColumn('document_sequences', 'last_modified_by')) {
                $table->dropForeign(['last_modified_by']);
            }
            foreach (['numbering_mode', 'is_locked', 'last_modified_by', 'last_modified_reason'] as $col) {
                if (Schema::hasColumn('document_sequences', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
