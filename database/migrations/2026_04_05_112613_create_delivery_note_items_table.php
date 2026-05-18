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
        Schema::create('delivery_note_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_note_id'); // FK différée

            $table->unsignedBigInteger('order_item_id')->nullable(); // FK différée
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->string('description', 255);

            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();

            $table->decimal('quantity', 10, 4);
            $table->decimal('unit_price', 15, 0)->default(0);

            $table->string('lot_number', 50)->nullable();
            $table->string('serial_number', 50)->nullable();
            $table->date('expiry_date')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_note_items');
    }
};
