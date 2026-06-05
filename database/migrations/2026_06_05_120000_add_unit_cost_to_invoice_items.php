<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [MARGES-CMP] Snapshot du coût unitaire (CMP) au moment de la validation de la
 * facture. Permet des marges historiques exactes — figées au coût réel à la date
 * de vente — au lieu d'utiliser le CMP/prix d'achat courant (volatil).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->default(0)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
