<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [DB-PERF-S2] Add missing indexes on company_id for accounting tables
 * and on products.brand_id (used as a filter in ProductRepository).
 *
 * accounts: composite (company_id, code) — covers the typical
 *   WHERE company_id = ? ORDER BY code pattern used in chart-of-accounts views.
 */
return new class extends Migration
{
    public function up(): void
    {
        // journal_entries.company_id
        Schema::table('journal_entries', function (Blueprint $table) {
            if (!$this->hasIndex('journal_entries', 'journal_entries_company_id_index')) {
                $table->index('company_id');
            }
        });

        // accounts — composite (company_id, code) covers both filter and ORDER BY code
        Schema::table('accounts', function (Blueprint $table) {
            if (!$this->hasIndex('accounts', 'accounts_company_id_code_index')) {
                $table->index(['company_id', 'code'], 'accounts_company_id_code_index');
            }
        });

        // products.brand_id
        Schema::table('products', function (Blueprint $table) {
            if (!$this->hasIndex('products', 'products_brand_id_index')) {
                $table->index('brand_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex('accounts_company_id_code_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['brand_id']);
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return count(DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        )) > 0;
    }
};
