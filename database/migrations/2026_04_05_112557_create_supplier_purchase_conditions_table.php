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
        Schema::create('supplier_purchase_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_reference', 80)->nullable(); // référence chez le fournisseur
            $table->decimal('purchase_price', 15, 0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('min_order_quantity', 10, 2)->default(1);
            $table->unsignedSmallInteger('lead_time_days')->default(0); // délai livraison
            $table->string('currency_code', 3)->default('XOF');
            $table->date('valid_until')->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_purchase_conditions');
    }
};
