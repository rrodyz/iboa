<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_returns', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('reception_id')->nullable()->constrained('receptions')->nullOnDelete();
            $table->foreignId('supplier_invoice_id')->nullable()->constrained('supplier_invoices')->nullOnDelete();

            $table->string('number', 30)->unique();

            $table->enum('status', [
                'brouillon',
                'valide',
                'envoye',
                'recu_fournisseur',
                'annule',
            ])->default('brouillon');

            $table->string('reason')->nullable();
            $table->date('returned_at');

            $table->string('currency_code', 3)->default('XOF');
            $table->decimal('exchange_rate', 15, 6)->default(1);

            $table->decimal('subtotal_ht', 15, 0)->default(0);
            $table->decimal('total_tax', 15, 0)->default(0);
            $table->decimal('total_ttc', 15, 0)->default(0);

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'status', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_returns');
    }
};
