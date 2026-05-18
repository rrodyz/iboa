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
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete(); // avoir lié à une facture

            // Identification
            $table->string('number', 30)->unique();
            $table->enum('status', ['brouillon', 'valide', 'applique', 'annule'])->default('brouillon');

            // Dates & motif
            $table->date('issued_at');
            $table->string('reason', 200)->nullable();

            // Devise
            $table->string('currency_code', 3)->default('XOF');

            // Montants (FCFA — 0 décimales)
            $table->decimal('subtotal_ht', 15, 0)->default(0);
            $table->decimal('total_tax', 15, 0)->default(0);
            $table->decimal('total_ttc', 15, 0)->default(0);
            $table->decimal('applied_amount', 15, 0)->default(0);   // montant utilisé
            $table->decimal('remaining_credit', 15, 0)->default(0); // solde avoir restant

            // Texte libre
            $table->text('notes')->nullable();

            // Workflow
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
