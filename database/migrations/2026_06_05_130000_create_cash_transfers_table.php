<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [TRESO] Virements internes — transfert d'argent entre comptes de trésorerie
 * (caisse <-> banque, banque <-> banque). Génère une écriture comptable double
 * (DR compte destination / CR compte source) et met à jour les soldes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transfers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained('companies');

            $table->foreignId('from_cash_account_id')
                  ->constrained('cash_accounts')
                  ->restrictOnDelete();

            $table->foreignId('to_cash_account_id')
                  ->constrained('cash_accounts')
                  ->restrictOnDelete();

            $table->string('number', 30)->unique();

            $table->decimal('amount', 15, 0);

            $table->date('transfer_date');

            // Réf bordereau / motif court
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();

            $table->enum('status', ['valide', 'annule'])->default('valide');

            $table->foreignId('journal_entry_id')
                  ->nullable()
                  ->nullOnDelete()
                  ->constrained('journal_entries');

            $table->foreignId('created_by')
                  ->nullable()
                  ->nullOnDelete()
                  ->constrained('users');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'transfer_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transfers');
    }
};
