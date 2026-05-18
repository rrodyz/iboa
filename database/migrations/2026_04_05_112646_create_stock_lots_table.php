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
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                  ->constrained('products')
                  ->restrictOnDelete();

            $table->foreignId('warehouse_id')
                  ->nullable()
                  ->nullOnDelete()
                  ->constrained('warehouses');

            $table->string('lot_number', 50);
            $table->string('serial_number', 50)->nullable();
            $table->date('expiry_date')->nullable();

            $table->decimal('quantity', 12, 4)->default(0);
            $table->decimal('unit_cost', 15, 0)->default(0);

            $table->date('received_at')->nullable();

            $table->enum('status', ['disponible', 'reserve', 'expire', 'consomme'])
                  ->default('disponible');

            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id', 'lot_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_lots');
    }
};
