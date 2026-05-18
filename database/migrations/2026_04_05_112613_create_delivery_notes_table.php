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
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('client_id')->constrained('clients');

            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->string('number', 30)->unique();
            $table->date('issued_at');

            $table->enum('status', ['brouillon', 'valide', 'livre', 'annule'])->default('brouillon');

            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->text('delivery_address')->nullable();

            $table->string('carrier', 100)->nullable();
            $table->string('tracking_number', 80)->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('currency_code', 3)->default('XOF');
            $table->decimal('total_quantity', 10, 4)->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_notes');
    }
};
