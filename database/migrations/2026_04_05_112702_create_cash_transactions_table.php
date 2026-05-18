<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cash_account_id')
                  ->constrained('cash_accounts')
                  ->restrictOnDelete();

            $table->enum('type', ['credit', 'debit']);

            // Polymorphic: client_payment, supplier_payment, etc.
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            // Montant en FCFA (0 décimales)
            $table->decimal('amount', 15, 0);

            // Solde du compte après cette transaction
            $table->decimal('balance_after', 15, 0);

            $table->string('label', 200)->nullable();

            $table->date('transaction_date');

            $table->foreignId('created_by')
                  ->nullable()
                  ->nullOnDelete()
                  ->constrained('users');

            $table->timestamps();

            $table->index(['cash_account_id', 'transaction_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
    }
};
