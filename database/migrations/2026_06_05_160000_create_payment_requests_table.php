<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [TRESO] Demandes de paiement — circuit demande → soumission → validation → paiement.
 * Une demande validée peut être convertie en décaissement (supplier_payment).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->foreignId('supplier_invoice_id')->nullable()->constrained('supplier_invoices')->nullOnDelete();

            $table->string('number', 30)->unique();
            $table->string('object', 255);            // objet de la demande
            $table->string('beneficiary', 150)->nullable(); // si pas de fournisseur enregistré
            $table->decimal('amount', 15, 0);
            $table->date('due_date')->nullable();
            $table->enum('priority', ['basse', 'normale', 'haute', 'urgente'])->default('normale');

            $table->enum('status', ['brouillon', 'soumis', 'valide', 'rejete', 'paye'])->default('brouillon');
            $table->string('required_role', 50)->nullable();

            $table->foreignId('supplier_payment_id')->nullable()->constrained('supplier_payments')->nullOnDelete();

            $table->text('notes')->nullable();

            $table->foreignId('requested_by')->nullable()->nullOnDelete()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('validated_by')->nullable()->nullOnDelete()->constrained('users');
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->nullOnDelete()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
