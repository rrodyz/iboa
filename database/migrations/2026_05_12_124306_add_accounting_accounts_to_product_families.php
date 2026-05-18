<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_families', function (Blueprint $table) {
            // Compte de vente (classe 7 — produits) — utilisé en automatique
            // lors de la comptabilisation des factures de vente
            $table->foreignId('sale_account_id')->nullable()->after('description')
                  ->constrained('accounts')->nullOnDelete()
                  ->comment('Compte comptable de vente (7xx)');

            // Compte d'achat (classe 6 — charges)
            $table->foreignId('purchase_account_id')->nullable()->after('sale_account_id')
                  ->constrained('accounts')->nullOnDelete()
                  ->comment('Compte comptable d\'achat (6xx)');

            // Compte de stock (classe 3 — stocks)
            $table->foreignId('stock_account_id')->nullable()->after('purchase_account_id')
                  ->constrained('accounts')->nullOnDelete()
                  ->comment('Compte comptable de stock (3xx)');
        });
    }

    public function down(): void
    {
        Schema::table('product_families', function (Blueprint $table) {
            $table->dropForeign(['sale_account_id']);
            $table->dropForeign(['purchase_account_id']);
            $table->dropForeign(['stock_account_id']);
            $table->dropColumn(['sale_account_id', 'purchase_account_id', 'stock_account_id']);
        });
    }
};
