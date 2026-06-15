<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [TRESO] Opérations diverses de caisse : entrées (apport, recette diverse)
 * et sorties (dépense diverse, petty cash) non liées à une facture tiers.
 * Chaque opération crée un mouvement (cash_transactions) + une écriture GL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('cash_account_id')->constrained('cash_accounts');
            $table->string('number', 30)->unique();
            $table->enum('direction', ['entree', 'sortie']);
            $table->string('category', 100)->nullable();
            $table->decimal('amount', 15, 0);
            $table->date('operation_date');
            $table->string('label', 255)->nullable();
            $table->enum('status', ['valide', 'annule'])->default('valide');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'cash_account_id', 'operation_date']);
            $table->index(['direction', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_operations');
    }
};
