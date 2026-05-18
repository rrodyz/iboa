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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('delivery_note_id')->nullable()->constrained('delivery_notes')->nullOnDelete();

            // Identification
            $table->string('number', 30)->unique();
            $table->enum('type', ['standard', 'acompte', 'partielle', 'recurrente', 'proforma'])->default('standard');
            $table->enum('status', ['brouillon', 'emise', 'envoyee', 'partiellement_payee', 'payee', 'en_retard', 'annulee'])->default('brouillon');

            // Dates
            $table->date('issued_at');
            $table->date('due_at')->nullable();

            // Devise
            $table->string('currency_code', 3)->default('XOF');
            $table->decimal('exchange_rate', 15, 6)->default(1);

            // Montants HT / TVA / TTC (FCFA — 0 décimales)
            $table->decimal('subtotal_ht', 15, 0)->default(0);
            $table->decimal('total_discount', 15, 0)->default(0);
            $table->decimal('total_tax', 15, 0)->default(0);
            $table->decimal('total_ttc', 15, 0)->default(0);

            // Paiement
            $table->decimal('paid_amount', 15, 0)->default(0);
            $table->decimal('remaining_amount', 15, 0)->default(0); // calculé

            // Remise globale
            $table->decimal('global_discount_percent', 5, 2)->default(0);
            $table->decimal('global_discount_amount', 15, 0)->default(0);

            // Adresse et textes libres
            $table->text('billing_address')->nullable();
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->text('footer_note')->nullable();
            $table->string('payment_terms', 100)->nullable();

            // Workflow
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            // Récurrence
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_frequency', 20)->nullable(); // mensuel/trimestriel/annuel
            $table->date('next_recurring_date')->nullable();
            $table->unsignedBigInteger('parent_invoice_id')->nullable(); // pour factures récurrentes

            $table->timestamps();
            $table->softDeletes();

            // Index composites
            $table->index(['client_id', 'status', 'issued_at']);
            $table->index(['due_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
