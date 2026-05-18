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
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('reception_id')->nullable()->constrained('receptions')->nullOnDelete();

            $table->string('number', 30)->unique();
            $table->string('supplier_invoice_number', 50)->nullable();

            $table->enum('status', [
                'recue',
                'validee',
                'en_litige',
                'payee',
                'partiellement_payee',
                'annulee',
            ])->default('recue');

            $table->date('received_at');
            $table->date('due_at')->nullable();

            $table->string('currency_code', 3)->default('XOF');
            $table->decimal('exchange_rate', 15, 6)->default(1);

            $table->decimal('subtotal_ht', 15, 0)->default(0);
            $table->decimal('total_tax', 15, 0)->default(0);
            $table->decimal('total_ttc', 15, 0)->default(0);
            $table->decimal('paid_amount', 15, 0)->default(0);
            $table->decimal('remaining_amount', 15, 0)->default(0);

            $table->text('notes')->nullable();
            $table->text('dispute_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'status', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
