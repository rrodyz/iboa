<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [ACHATS-PRO-SCHEDULE] Cadenciers de paiement (échéanciers multiples).
 *
 * Une facture fournisseur peut être découpée en N échéances :
 *   ex. : 30% à 30j · 40% à 60j · 30% à 90j
 * Statut par ligne : en_attente · partiel · paye · annule
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->date('due_date');
            $table->decimal('amount', 18, 4);
            $table->decimal('paid_amount', 18, 4)->default(0);
            $table->enum('status', ['en_attente','partiel','paye','annule'])->default('en_attente');
            $table->string('label', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['supplier_invoice_id', 'installment_number']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_schedules');
    }
};
