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
        Schema::create('client_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['facturation', 'livraison', 'autre'])->default('livraison');
            $table->string('label', 80)->nullable();          // "Siège", "Dépôt Nord"
            $table->string('recipient', 150)->nullable();
            $table->string('address', 255);
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('country', 80)->default('Burkina Faso');
            $table->string('phone', 20)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_addresses');
    }
};
