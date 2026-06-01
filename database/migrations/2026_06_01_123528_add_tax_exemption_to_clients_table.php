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
            // Exonération TVA — règle métier : si is_tax_exempt = true,
            // aucune TVA n'est appliquée sur aucun document de vente.
            $table->boolean('is_tax_exempt')
                  ->default(false)
                  ->after('tax_rate_id')
                  ->comment('Client exonéré de TVA (TVA = 0 sur tous les documents)');

            $table->string('tax_exemption_reason', 200)
                  ->nullable()
                  ->after('is_tax_exempt')
                  ->comment('Motif d\'exonération (ex: Organisme exonéré, Exportation, Zone franche…)');

            $table->string('tax_exemption_number', 100)
                  ->nullable()
                  ->after('tax_exemption_reason')
                  ->comment('Numéro du document d\'exonération (attestation DGI, agrément…)');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['is_tax_exempt', 'tax_exemption_reason', 'tax_exemption_number']);
        });
    }
};
