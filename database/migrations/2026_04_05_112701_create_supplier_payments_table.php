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
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                  ->constrained('companies');

            $table->foreignId('supplier_id')
                  ->constrained('suppliers')
                  ->restrictOnDelete();

            $table->foreignId('cash_account_id')
                  ->nullable()
                  ->nullOnDelete()
                  ->constrained('cash_accounts');

            $table->foreignId('payment_method_id')
                  ->nullable()
                  ->nullOnDelete()
                  ->constrained('payment_methods');

            $table->string('number', 30)->unique();

            // Montant en FCFA (0 décimales)
            $table->decimal('amount', 15, 0);

            $table->string('currency_code', 3)->default('XOF');
            $table->decimal('exchange_rate', 15, 6)->default(1);

            $table->date('payment_date');

            // N° chèque, ref virement, transaction Mobile Money
            $table->string('reference', 100)->nullable();

            // Pour Mobile Money
            $table->string('phone_number', 20)->nullable();

            $table->enum('status', ['en_attente', 'confirme', 'rejete', 'annule'])
                  ->default('confirme');

            $table->text('notes')->nullable();

            $table->decimal('allocated_amount', 15, 0)->default(0);
            $table->decimal('unallocated_amount', 15, 0)->default(0);

            $table->foreignId('created_by')
                  ->nullable()
                  ->nullOnDelete()
                  ->constrained('users');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'payment_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
