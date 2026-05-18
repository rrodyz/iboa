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
        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                  ->constrained('companies');

            $table->string('name', 100);
            $table->string('code', 20)->unique();

            $table->enum('type', ['caisse', 'banque', 'mobile_money'])->default('caisse');

            $table->foreignId('payment_method_id')
                  ->nullable()
                  ->nullOnDelete()
                  ->constrained('payment_methods');

            $table->string('currency_code', 3)->default('XOF');

            // Solde initial (FCFA, 0 décimales)
            $table->decimal('opening_balance', 15, 0)->default(0);

            // Solde courant calculé (FCFA, 0 décimales)
            $table->decimal('current_balance', 15, 0)->default(0);

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_accounts');
    }
};
