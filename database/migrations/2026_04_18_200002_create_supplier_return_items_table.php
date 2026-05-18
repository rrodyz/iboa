<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_return_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_return_id')->constrained('supplier_returns')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();

            $table->string('description')->default('');
            $table->decimal('quantity', 15, 3)->default(1);
            $table->decimal('unit_price', 15, 0)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('tax_rate_value', 5, 2)->default(0);

            $table->decimal('line_total_ht', 15, 0)->default(0);
            $table->decimal('line_tax', 15, 0)->default(0);
            $table->decimal('line_total_ttc', 15, 0)->default(0);

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['supplier_return_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_return_items');
    }
};
