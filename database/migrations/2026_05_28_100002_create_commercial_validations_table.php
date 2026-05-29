<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table d'audit trail pour toutes les transitions de statut
 * des documents commerciaux (devis, commandes, BL, factures, avoirs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_validations', function (Blueprint $table) {
            $table->id();

            // Document concerné (polymorphique)
            $table->string('document_type', 50);       // 'quote','order','delivery_note','invoice','credit_note'
            $table->unsignedBigInteger('document_id');

            // Transition
            $table->string('ancien_statut', 50)->nullable();
            $table->string('nouveau_statut', 50);
            $table->string('action', 50);              // 'soumission','validation','refus','annulation','transformation'

            // Acteur
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('user_role', 100)->nullable();   // rôle au moment de l'action

            // Contexte
            $table->text('motif')->nullable();              // obligatoire pour refus/annulation
            $table->string('ip_address', 45)->nullable();   // IPv4 et IPv6
            $table->string('user_agent', 500)->nullable();

            // Metadata
            $table->json('metadata')->nullable();           // données supplémentaires libres

            $table->timestamp('created_at')->useCurrent();
            // Pas de updated_at : audit trail immuable

            // Index pour les requêtes courantes
            $table->index(['document_type', 'document_id'], 'idx_cv_document');
            $table->index(['user_id', 'created_at'],         'idx_cv_user_date');
            $table->index(['action', 'created_at'],           'idx_cv_action_date');
            $table->index('created_at',                       'idx_cv_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_validations');
    }
};
