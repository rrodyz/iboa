<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_effects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            $table->string('number', 30)->unique();

            $table->enum('type', ['cheque', 'lcr', 'billet_ordre', 'traite'])->default('lcr');
            $table->enum('direction', ['a_recevoir', 'a_payer'])->default('a_recevoir');

            // Tiers
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();

            // Facture liée
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('supplier_invoice_id')->nullable()
                  ->constrained('supplier_invoices')->nullOnDelete();

            $table->decimal('amount', 15, 0);
            $table->string('currency_code', 3)->default('XOF');

            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->date('acceptance_date')->nullable();
            $table->date('payment_date')->nullable();          // actual payment date

            $table->enum('status', [
                'en_attente',
                'accepte',
                'remis_banque',
                'encaisse',
                'rejete',
                'proteste',
                'annule',
            ])->default('en_attente');

            // Parties
            $table->string('drawer', 150)->nullable();         // tireur
            $table->string('drawee', 150)->nullable();         // tiré
            $table->string('payee', 150)->nullable();          // bénéficiaire

            // Bank domiciliation
            $table->string('bank_name', 150)->nullable();
            $table->string('bank_account', 100)->nullable();
            $table->string('reference', 100)->nullable();      // chèque n°, etc.

            // When remitted to bank
            $table->foreignId('bank_deposit_id')->nullable();
            $table->foreignId('cash_account_id')->nullable()
                  ->constrained('cash_accounts')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'due_date']);
            $table->index(['company_id', 'status']);
            $table->index('client_id');
            $table->index('supplier_id');
        });

        // Add FK on bank_deposit_items after commercial_effects exists
        Schema::table('bank_deposit_items', function (Blueprint $table) {
            $table->foreign('commercial_effect_id')
                  ->references('id')->on('commercial_effects')->nullOnDelete();
        });

        // Add FK on commercial_effects.bank_deposit_id after bank_deposits exists
        Schema::table('commercial_effects', function (Blueprint $table) {
            $table->foreign('bank_deposit_id')
                  ->references('id')->on('bank_deposits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commercial_effects', function (Blueprint $table) {
            $table->dropForeign(['bank_deposit_id']);
        });
        Schema::table('bank_deposit_items', function (Blueprint $table) {
            $table->dropForeign(['commercial_effect_id']);
        });
        Schema::dropIfExists('commercial_effects');
    }
};
