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
        Schema::table('document_sequences', function (Blueprint $table) {
            // Whether to include the year in the generated number (e.g. FA-2026-001)
            $table->boolean('include_year')->default(true)->after('padding');
            // '4' = YYYY (2026), '2' = YY (26)
            $table->string('year_format', 1)->default('4')->after('include_year');
            // Separator between year and padded number (default: -)
            $table->string('year_separator', 5)->default('-')->after('year_format');
        });
    }

    public function down(): void
    {
        Schema::table('document_sequences', function (Blueprint $table) {
            $table->dropColumn(['include_year', 'year_format', 'year_separator']);
        });
    }
};
