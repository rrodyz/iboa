<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes manquants identifiés lors de l'audit de performance.
 * Chaque index est conditionnel (hasIndex check) pour éviter les erreurs
 * si certains existent déjà partiellement.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── delivery_notes : aucun index hors PK ─────────────────────────────
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->index(['client_id', 'status'],    'dn_client_status_idx');
            $table->index(['order_id'],               'dn_order_idx');
            $table->index(['status', 'issued_at'],    'dn_status_issued_idx');
            $table->index(['company_id', 'status'],   'dn_company_status_idx');
        });

        // ── client_payments : index sur status manquant ───────────────────────
        Schema::table('client_payments', function (Blueprint $table) {
            $table->index(['status'],                            'cp_status_idx');
            $table->index(['client_id', 'status', 'payment_date'], 'cp_client_status_date_idx');
        });

        // ── supplier_payments : même manque ──────────────────────────────────
        if (Schema::hasTable('supplier_payments')) {
            Schema::table('supplier_payments', function (Blueprint $table) {
                $table->index(['status'],                               'sp_status_idx');
                $table->index(['supplier_id', 'status', 'payment_date'], 'sp_supplier_status_date_idx');
            });
        }

        // ── stock_movements : index sur type ─────────────────────────────────
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['type', 'occurred_at'], 'sm_type_date_idx');
            $table->index(['product_id', 'type'],  'sm_product_type_idx');
        });

        // ── invoices : company_id et fiscal_year_id ───────────────────────────
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'company_id')) {
                $table->index(['company_id', 'status', 'issued_at'], 'inv_company_status_idx');
            }
            if (Schema::hasColumn('invoices', 'fiscal_year_id')) {
                $table->index(['fiscal_year_id'], 'inv_fiscal_year_idx');
            }
        });

        // ── journal_entry_lines : index composite comptable ───────────────────
        if (Schema::hasTable('journal_entry_lines')) {
            Schema::table('journal_entry_lines', function (Blueprint $table) {
                $table->index(['account_id', 'debit'],  'jel_account_debit_idx');
                $table->index(['account_id', 'credit'], 'jel_account_credit_idx');
            });
        }

        // ── orders : index status + issued_at ────────────────────────────────
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index(['status', 'issued_at'], 'ord_status_issued_idx');
            });
        }

        // ── quotes : index status + issued_at ────────────────────────────────
        if (Schema::hasTable('quotes')) {
            Schema::table('quotes', function (Blueprint $table) {
                $table->index(['client_id', 'status'], 'q_client_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropIndex('dn_client_status_idx');
            $table->dropIndex('dn_order_idx');
            $table->dropIndex('dn_status_issued_idx');
            $table->dropIndex('dn_company_status_idx');
        });

        Schema::table('client_payments', function (Blueprint $table) {
            $table->dropIndex('cp_status_idx');
            $table->dropIndex('cp_client_status_date_idx');
        });

        if (Schema::hasTable('supplier_payments')) {
            Schema::table('supplier_payments', function (Blueprint $table) {
                $table->dropIndex('sp_status_idx');
                $table->dropIndex('sp_supplier_status_date_idx');
            });
        }

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('sm_type_date_idx');
            $table->dropIndex('sm_product_type_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'company_id')) {
                $table->dropIndex('inv_company_status_idx');
            }
            if (Schema::hasColumn('invoices', 'fiscal_year_id')) {
                $table->dropIndex('inv_fiscal_year_idx');
            }
        });

        if (Schema::hasTable('journal_entry_lines')) {
            Schema::table('journal_entry_lines', function (Blueprint $table) {
                $table->dropIndex('jel_account_debit_idx');
                $table->dropIndex('jel_account_credit_idx');
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('ord_status_issued_idx');
            });
        }

        if (Schema::hasTable('quotes')) {
            Schema::table('quotes', function (Blueprint $table) {
                $table->dropIndex('q_client_status_idx');
            });
        }
    }
};
