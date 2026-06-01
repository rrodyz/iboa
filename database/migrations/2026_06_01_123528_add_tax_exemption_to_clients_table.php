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
        // Position d'insertion robuste : on s'ancre sur tax_rate_id s'il existe,
        // sinon on laisse MySQL ajouter en fin de table (la migration repair_clients
        // peut être encore en attente dans certains environnements de dev).
        $anchor = Schema::hasColumn('clients', 'tax_rate_id') ? 'tax_rate_id' : null;

        Schema::table('clients', function (Blueprint $table) use ($anchor) {
            // Exonération TVA — règle métier : si is_tax_exempt = true,
            // aucune TVA n'est appliquée sur aucun document de vente.
            if (! Schema::hasColumn('clients', 'is_tax_exempt')) {
                $col = $table->boolean('is_tax_exempt')->default(false)
                    ->comment('Client exonéré de TVA (TVA = 0 sur tous les documents)');
                if ($anchor) $col->after($anchor);
            }

            if (! Schema::hasColumn('clients', 'tax_exemption_reason')) {
                $table->string('tax_exemption_reason', 200)->nullable()
                    ->comment('Motif d\'exonération (ex: Organisme exonéré, Exportation, Zone franche…)');
            }

            if (! Schema::hasColumn('clients', 'tax_exemption_number')) {
                $table->string('tax_exemption_number', 100)->nullable()
                    ->comment('Numéro du document d\'exonération (attestation DGI, agrément…)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['is_tax_exempt', 'tax_exemption_reason', 'tax_exemption_number']);
        });
    }
};
