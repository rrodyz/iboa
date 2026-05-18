<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajoute le type de taxe : 'tva' (collectée) ou 'retenue' (déduite du net à payer)
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->string('type', 20)->default('tva')->after('is_default')
                  ->comment('tva = TVA collectée | retenue = retenue à la source (BIC, etc.)');
        });

        // Ajoute les colonnes de retenue sur les factures
        Schema::table('invoices', function (Blueprint $table) {
            // Détail de chaque retenue appliquée [{name, short_name, rate, amount}]
            $table->json('withholding_details')->nullable()->after('global_discount_amount');
            // Montant total des retenues (somme de toutes les retenues)
            $table->integer('withholding_amount')->default(0)->after('withholding_details');
            // Net à payer = total_ttc - withholding_amount
            $table->integer('net_to_pay')->default(0)->after('withholding_amount');
        });
    }

    public function down(): void
    {
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->dropColumn('type');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['withholding_details', 'withholding_amount', 'net_to_pay']);
        });
    }
};
