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
        Schema::table('clients', function (Blueprint $table) {
            // Place both columns right after 'rccm' for logical grouping
            $table->string('tax_regime', 100)->nullable()->after('rccm')
                  ->comment('Régime fiscal : RNI, RIS, TF, Auto-Entrepreneur…');
            $table->string('tax_division', 150)->nullable()->after('tax_regime')
                  ->comment('Division / centre fiscal de rattachement');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['tax_regime', 'tax_division']);
        });
    }
};
