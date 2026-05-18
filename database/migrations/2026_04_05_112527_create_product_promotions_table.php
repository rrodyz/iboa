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
        Schema::create('product_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->enum('type', ['pourcentage', 'montant_fixe', 'prix_special'])->default('pourcentage');
            $table->decimal('value', 10, 2);                  // % ou montant ou prix
            $table->date('starts_at');
            $table->date('ends_at');
            $table->decimal('min_quantity', 10, 2)->nullable();
            $table->decimal('min_amount', 15, 0)->nullable();
            // Portée : NULL = tous produits ; rempli = ciblé
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('family_id')->nullable()->constrained('product_families')->cascadeOnDelete();
            $table->unsignedBigInteger('client_id')->nullable(); // FK ajoutée après clients
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_promotions');
    }
};
