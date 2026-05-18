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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years');

            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();

            $table->string('number', 30)->unique();
            $table->string('reference', 50)->nullable();

            $table->enum('status', [
                'brouillon', 'confirme', 'en_preparation', 'partiellement_livre',
                'livre', 'facture', 'annule',
            ])->default('brouillon');

            $table->date('issued_at');
            $table->date('expires_at')->nullable();
            $table->date('delivery_date')->nullable();

            $table->foreignId('delivery_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->text('delivery_address')->nullable();
            $table->text('billing_address')->nullable();

            $table->string('currency_code', 3)->default('XOF');
            $table->decimal('exchange_rate', 15, 6)->default(1);

            $table->decimal('subtotal_ht', 15, 0)->default(0);
            $table->decimal('total_discount', 15, 0)->default(0);
            $table->decimal('total_tax', 15, 0)->default(0);
            $table->decimal('total_ttc', 15, 0)->default(0);

            $table->decimal('global_discount_percent', 5, 2)->default(0);
            $table->decimal('global_discount_amount', 15, 0)->default(0);

            $table->decimal('invoiced_amount', 15, 0)->default(0);

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->text('footer_note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status', 'issued_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
