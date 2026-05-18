<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer la colonne tax_rate_id ajoutée précédemment (relation 1-1 remplacée par N-N)
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->dropColumn('tax_rate_id');
        });

        // Table pivot N-N clients <-> tax_rates
        Schema::create('client_tax_rates', function (Blueprint $table) {
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('tax_rate_id')->constrained('tax_rates')->cascadeOnDelete();
            $table->primary(['client_id', 'tax_rate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_tax_rates');

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')
                  ->nullable()
                  ->after('tax_division')
                  ->constrained('tax_rates')
                  ->nullOnDelete();
        });
    }
};
