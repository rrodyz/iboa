<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [CONCURRENCE-MULTI-USER] Verrous pessimistes d'édition.
 *
 * Garantit qu'un seul utilisateur édite un document à la fois.
 * TTL : 15 min renouvelable par ping JS côté client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edit_locks', function (Blueprint $table) {
            $table->id();

            // Polymorphique : fonctionne avec Invoice, Quote, PurchaseOrder, etc.
            $table->string('lockable_type', 150);
            $table->unsignedBigInteger('lockable_id');

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_id', 128);

            $table->timestamp('locked_at');
            $table->timestamp('expires_at');

            $table->timestamps();

            // Une seule entrée par document — pas de doublon de verrou
            $table->unique(['lockable_type', 'lockable_id'], 'unique_edit_lock');
            $table->index('expires_at', 'idx_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edit_locks');
    }
};
