<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [TRESO] Promesses de paiement client : engagement de régler un montant à
 * une date donnée (suivi du recouvrement). Pas d'impact comptable — simple
 * engagement suivi (tenue / non tenue).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_promises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->decimal('amount', 15, 0);
            $table->date('promised_date');
            $table->enum('status', ['en_attente', 'tenue', 'non_tenue', 'annulee'])->default('en_attente');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['client_id', 'promised_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_promises');
    }
};
