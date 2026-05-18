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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // Identification
            $table->string('reference', 50)->unique();
            $table->string('barcode', 50)->nullable()->unique();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            // Classification
            $table->foreignId('family_id')->nullable()->constrained('product_families')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            // Type
            $table->enum('type', ['simple', 'service', 'compose'])->default('simple');
            $table->boolean('is_stockable')->default(true);
            $table->boolean('is_purchasable')->default(true);
            $table->boolean('is_sellable')->default(true);
            // Prix
            $table->decimal('purchase_price', 15, 0)->default(0);    // FCFA 0 décimales
            $table->decimal('sale_price', 15, 0)->default(0);
            $table->decimal('min_sale_price', 15, 0)->default(0);    // prix plancher
            // Stocks
            $table->decimal('stock_min', 10, 2)->default(0);
            $table->decimal('stock_max', 10, 2)->nullable();
            $table->decimal('reorder_point', 10, 2)->default(0);
            $table->enum('valuation_method', ['cmp', 'fifo', 'lifo'])->default('cmp');
            // Dimensions (optionnel)
            $table->decimal('weight', 10, 3)->nullable();
            $table->string('weight_unit', 5)->nullable();
            // Numéro de série / lot
            $table->boolean('has_serial_number')->default(false);
            $table->boolean('has_lot_number')->default(false);
            $table->boolean('has_expiry_date')->default(false);
            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['family_id', 'is_active']);
            $table->index('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
