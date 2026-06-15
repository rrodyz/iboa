<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [TRESO] Clôtures journalières de caisse — contrôle solde théorique vs compté.
 * À la validation, l'écart éventuel génère une écriture (6588 manque / 7588 excédent)
 * et ajuste le solde de la caisse à la réalité physique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_closures', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();

            $table->string('number', 30)->unique();
            $table->date('closure_date');

            // Théorique (système) vs compté (physique)
            $table->decimal('theoretical_balance', 15, 0)->default(0);
            $table->decimal('counted_balance', 15, 0)->default(0);
            $table->decimal('difference', 15, 0)->default(0); // counted - theoretical

            // Détail dénominations (billets/pièces) — optionnel
            $table->json('denominations')->nullable();

            $table->enum('status', ['brouillon', 'valide'])->default('brouillon');
            $table->text('difference_reason')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('journal_entry_id')->nullable()->nullOnDelete()->constrained('journal_entries');

            $table->foreignId('created_by')->nullable()->nullOnDelete()->constrained('users');
            $table->foreignId('validated_by')->nullable()->nullOnDelete()->constrained('users');
            $table->timestamp('validated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['cash_account_id', 'closure_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_closures');
    }
};
