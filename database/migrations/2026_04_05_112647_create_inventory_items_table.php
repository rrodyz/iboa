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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('inventory_session_id'); // FK différée

            $table->foreignId('product_id')
                  ->nullable()
                  ->nullOnDelete()
                  ->constrained('products');

            $table->foreignId('warehouse_id')
                  ->nullable()
                  ->nullOnDelete()
                  ->constrained('warehouses');

            // Stock théorique (système)
            $table->decimal('theoretical_quantity', 12, 4)->default(0);

            // Quantité comptée physiquement
            $table->decimal('counted_quantity', 12, 4)->nullable();

            // Écart = compté - théorique
            $table->decimal('variance_quantity', 12, 4)->default(0);

            $table->decimal('unit_cost', 15, 0)->default(0);

            // Valeur de l'écart (FCFA, 0 décimales)
            $table->decimal('variance_value', 15, 0)->default(0);

            $table->string('lot_number', 50)->nullable();

            $table->timestamp('counted_at')->nullable();

            $table->foreignId('counted_by')
                  ->nullable()
                  ->nullOnDelete()
                  ->constrained('users');

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
