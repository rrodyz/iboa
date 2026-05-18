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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            // Identification
            $table->string('code', 20)->unique();
            $table->enum('type', ['particulier', 'entreprise'])->default('entreprise');
            $table->string('name', 150);                      // Raison sociale ou nom
            $table->string('trade_name', 150)->nullable();
            $table->string('civility', 10)->nullable();       // M., Mme, Dr...
            // Coordonnées
            $table->string('phone', 20)->nullable();
            $table->string('phone2', 20)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('website', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 80)->default('Burkina Faso');
            // Informations légales
            $table->string('ifu', 30)->nullable();
            $table->string('rccm', 50)->nullable();
            // Segmentation commerciale
            $table->string('category', 50)->nullable();       // gros, semi-gros, detail
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); // commercial
            // Conditions financières
            $table->decimal('credit_limit', 15, 0)->default(0);
            $table->unsignedSmallInteger('payment_days')->default(0); // délai de paiement en jours
            $table->string('payment_terms', 50)->nullable();  // "30 jours net"
            $table->decimal('default_discount', 5, 2)->default(0); // remise habituelle %
            // Balance
            $table->decimal('balance', 15, 0)->default(0);   // solde courant (calculé)
            // Status
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['name', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
