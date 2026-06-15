<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [TRESO-P7] Index de performance pour les requêtes chaudes de trésorerie.
 * - supplier_payments : liste des décaissements en attente de validation.
 * - cash_closures : garde post-clôture (cash_account_id + status + closure_date).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            if (! $this->hasIndex('supplier_payments', 'sp_company_validation_idx')) {
                $table->index(['company_id', 'validation_status'], 'sp_company_validation_idx');
            }
        });

        Schema::table('cash_closures', function (Blueprint $table) {
            if (! $this->hasIndex('cash_closures', 'cc_account_status_date_idx')) {
                $table->index(['cash_account_id', 'status', 'closure_date'], 'cc_account_status_date_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropIndex('sp_company_validation_idx');
        });

        Schema::table('cash_closures', function (Blueprint $table) {
            $table->dropIndex('cc_account_status_date_idx');
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        foreach (Schema::getIndexes($table) as $existing) {
            if (($existing['name'] ?? null) === $index) {
                return true;
            }
        }

        return false;
    }
};
