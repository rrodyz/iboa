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
        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->id();

            // FK ajoutée en différé (supplier_payments créée après alphabétiquement)
            $table->unsignedBigInteger('supplier_payment_id');

            $table->foreignId('supplier_invoice_id')
                  ->nullable()
                  ->nullOnDelete()
                  ->constrained('supplier_invoices');

            // Montant lettré (FCFA, 0 décimales)
            $table->decimal('amount', 15, 0);

            $table->timestamp('allocated_at');

            $table->foreignId('created_by')
                  ->nullable()
                  ->nullOnDelete()
                  ->constrained('users');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_allocations');
    }
};
