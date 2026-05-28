<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive uniquement — tous les champs sont nullables.
     * Les bulletins existants conservent bulletin_number = null.
     */
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->string('bulletin_number', 50)->nullable()->after('id')
                  ->comment('Numéro généré ex: BUL-2026-05-0001');

            $table->foreignId('numbering_id')->nullable()->after('bulletin_number')
                  ->constrained('payroll_numberings')->nullOnDelete();

            $table->foreignId('template_id')->nullable()->after('numbering_id')
                  ->constrained('payroll_bulletin_templates')->nullOnDelete();

            $table->index('bulletin_number');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropForeign(['numbering_id']);
            $table->dropForeign(['template_id']);
            $table->dropIndex(['bulletin_number']);
            $table->dropColumn(['bulletin_number', 'numbering_id', 'template_id']);
        });
    }
};
