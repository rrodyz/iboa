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
        Schema::create('reception_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reception_id')->constrained('receptions')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->string('description', 255);

            $table->foreignId('unit_id')->nullable()->constrained('units');

            $table->decimal('expected_quantity', 10, 4)->default(0);
            $table->decimal('received_quantity', 10, 4)->default(0);
            $table->decimal('rejected_quantity', 10, 4)->default(0);

            $table->decimal('unit_cost', 15, 0)->default(0);

            $table->string('lot_number', 50)->nullable();
            $table->date('expiry_date')->nullable();

            $table->enum('quality_status', ['accepte', 'rejete', 'en_attente'])->default('accepte');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reception_items');
    }
};
