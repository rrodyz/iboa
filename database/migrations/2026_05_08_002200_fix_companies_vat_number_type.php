<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [DB-FIX] companies.vat_number was incorrectly typed as DECIMAL(3,0).
 * A VAT / IFU registration number is a string (e.g. "BF-IFU-123456789"),
 * not a numeric value. Changed to VARCHAR(30).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('vat_number', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('vat_number', 3, 0)->nullable()->change();
        });
    }
};
