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
        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->unsignedBigInteger('credit_note_id'); // FK différée
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            // Description ligne
            $table->string('description', 255);
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();

            // Quantité & prix (FCFA — 0 décimales sur les montants)
            $table->decimal('quantity', 10, 4);
            $table->decimal('unit_price', 15, 0);

            // TVA — snapshot du taux au moment de l'avoir
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_rate_value', 5, 2)->default(0);

            // Totaux ligne
            $table->decimal('line_total_ht', 15, 0);
            $table->decimal('line_tax', 15, 0);
            $table->decimal('line_total_ttc', 15, 0);

            // Ordre d'affichage
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_note_items');
    }
};
