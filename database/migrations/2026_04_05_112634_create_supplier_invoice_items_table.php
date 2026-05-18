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
        Schema::create('supplier_invoice_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('supplier_invoice_id'); // FK différée
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->string('description', 255);

            $table->foreignId('unit_id')->nullable()->constrained('units');

            $table->decimal('quantity', 10, 4);
            $table->decimal('unit_price', 15, 0);

            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_rate_value', 5, 2)->default(0);

            $table->decimal('line_total_ht', 15, 0);
            $table->decimal('line_tax', 15, 0);
            $table->decimal('line_total_ttc', 15, 0);

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_items');
    }
};
