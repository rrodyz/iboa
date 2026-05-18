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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();

            // Informations générales
            $table->string('name', 150);
            $table->string('trade_name', 150)->nullable();   // nom commercial
            $table->string('slogan', 255)->nullable();
            $table->string('logo')->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('country', 100)->default('Burkina Faso');
            $table->string('postal_code', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('phone2', 20)->nullable();
            $table->string('fax', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('website', 150)->nullable();

            // Informations légales
            $table->string('legal_form', 50)->nullable();    // SARL, SA, SAS, EI...
            $table->string('rccm', 50)->nullable();          // Registre du Commerce
            $table->string('ifu', 30)->nullable();           // Identifiant Fiscal Unique
            $table->string('nif', 30)->nullable();           // Numéro Identification Fiscale
            $table->boolean('is_vat_subject')->default(false);
            $table->decimal('vat_number', 3, 0)->nullable(); // % TVA par défaut
            $table->decimal('share_capital', 15, 0)->nullable();
            $table->string('share_capital_currency', 3)->default('XOF');

            // Paramètres comptables
            $table->foreignId('default_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('current_fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
