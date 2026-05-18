<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrichissement de la table products pour matcher Sage GesCom :
 *  - Comptes comptables spécifiques (sale/purchase/stock) au niveau article
 *    (fallback sur la famille si null — déjà géré dans AccountingService)
 *  - Fournisseur principal + référence fournisseur + délai
 *  - Marge cible + dernier prix d'achat + PMP (auto-calculés)
 *
 * Tous les champs sont nullable / valeur par défaut sûre : aucune régression
 * sur les articles existants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {

            // ── Comptes comptables (override de la famille) ────────────────
            if (!Schema::hasColumn('products', 'sale_account_id')) {
                $table->foreignId('sale_account_id')
                      ->nullable()->after('tax_rate_id')
                      ->constrained('accounts')->nullOnDelete()
                      ->comment('Compte de vente (701x) — override famille');
            }
            if (!Schema::hasColumn('products', 'purchase_account_id')) {
                $table->foreignId('purchase_account_id')
                      ->nullable()->after('sale_account_id')
                      ->constrained('accounts')->nullOnDelete()
                      ->comment('Compte d achat (601x) — override famille');
            }
            if (!Schema::hasColumn('products', 'stock_account_id')) {
                $table->foreignId('stock_account_id')
                      ->nullable()->after('purchase_account_id')
                      ->constrained('accounts')->nullOnDelete()
                      ->comment('Compte de stock (311x) — override famille');
            }

            // ── Fournisseur principal ──────────────────────────────────────
            if (!Schema::hasColumn('products', 'default_supplier_id')) {
                $table->foreignId('default_supplier_id')
                      ->nullable()->after('brand_id')
                      ->constrained('suppliers')->nullOnDelete();
            }
            if (!Schema::hasColumn('products', 'supplier_reference')) {
                $table->string('supplier_reference', 80)->nullable()->after('default_supplier_id');
            }
            if (!Schema::hasColumn('products', 'delivery_delay_days')) {
                $table->unsignedSmallInteger('delivery_delay_days')->nullable()->after('supplier_reference');
            }

            // ── Marges & coûts ─────────────────────────────────────────────
            if (!Schema::hasColumn('products', 'margin_rate_target')) {
                $table->decimal('margin_rate_target', 5, 2)->nullable()->after('min_sale_price')
                      ->comment('Taux de marge cible (% — utilisé pour suggérer un prix de vente)');
            }
            if (!Schema::hasColumn('products', 'last_purchase_price')) {
                $table->decimal('last_purchase_price', 15, 0)->default(0)->after('purchase_price')
                      ->comment('Dernier prix d achat - mis a jour auto');
            }
            if (!Schema::hasColumn('products', 'weighted_avg_cost')) {
                $table->decimal('weighted_avg_cost', 15, 2)->default(0)->after('last_purchase_price')
                      ->comment('Prix moyen pondéré (PMP) — mis à jour à chaque entrée stock');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach ([
                'sale_account_id', 'purchase_account_id', 'stock_account_id',
                'default_supplier_id', 'supplier_reference', 'delivery_delay_days',
                'margin_rate_target', 'last_purchase_price', 'weighted_avg_cost',
            ] as $col) {
                if (Schema::hasColumn('products', $col)) {
                    try { $table->dropForeign([$col]); } catch (\Throwable $e) {}
                    $table->dropColumn($col);
                }
            }
        });
    }
};
