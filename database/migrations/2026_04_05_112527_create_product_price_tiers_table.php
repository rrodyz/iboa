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
        // Tarifs spéciaux par client ou catégorie de client
        Schema::create('product_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Applicable à un client spécifique OU à une catégorie (null = tous)
            $table->unsignedBigInteger('client_id')->nullable(); // FK ajoutée après clients dans add_fk_migrations
            $table->string('client_category', 50)->nullable(); // gros, semi-gros, detail
            $table->string('label', 80)->nullable();
            $table->decimal('price', 15, 0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->decimal('min_quantity', 10, 2)->default(0); // qté min pour ce tarif
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_price_tiers');
    }
};
