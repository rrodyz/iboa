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
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();             // ISO 4217 : XOF, EUR, USD
            $table->string('name', 80);                      // Franc CFA BCEAO
            $table->string('symbol', 10);                    // FCFA, €, $
            $table->unsignedTinyInteger('decimal_places')->default(0);
            $table->char('thousands_separator', 1)->default(' ');
            $table->char('decimal_separator', 1)->default(',');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
