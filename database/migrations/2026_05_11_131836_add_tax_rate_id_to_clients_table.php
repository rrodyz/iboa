<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')
                  ->nullable()
                  ->after('tax_division')
                  ->constrained('tax_rates')
                  ->nullOnDelete()
                  ->comment('Taux de TVA par défaut appliqué aux factures de ce client');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->dropColumn('tax_rate_id');
        });
    }
};
