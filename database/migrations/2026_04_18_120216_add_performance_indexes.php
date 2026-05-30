<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index de performance pour les requêtes fréquentes du dashboard,
     * des listes et des rapports.
     */
    public function up(): void
    {
        // ── client_payments ──────────────────────────────────────────────────
        $this->addIndexIfNotExists('client_payments', ['status', 'payment_date'], 'idx_cp_status_date');

        // ── supplier_payments ─────────────────────────────────────────────────
        $this->addIndexIfNotExists('supplier_payments', ['status', 'payment_date'], 'idx_sp_status_date');

        // ── orders ────────────────────────────────────────────────────────────
        $this->addIndexIfNotExists('orders', ['status', 'issued_at'], 'idx_orders_status_date');

        // ── quotes ────────────────────────────────────────────────────────────
        $this->addIndexIfNotExists('quotes', ['status', 'issued_at'], 'idx_quotes_status_date');
        $this->addIndexIfNotExists('quotes', ['expires_at'],          'idx_quotes_expires_at');

        // ── stock_movements ───────────────────────────────────────────────────
        $this->addIndexIfNotExists('stock_movements', ['type', 'created_at'],                    'idx_sm_type_date');
        $this->addIndexIfNotExists('stock_movements', ['product_id', 'warehouse_id', 'type'],    'idx_sm_product_warehouse_type');

        // ── product_stocks ────────────────────────────────────────────────────
        $this->addIndexIfNotExists('product_stocks', ['quantity'], 'idx_ps_quantity');

        // ── audit_logs ────────────────────────────────────────────────────────
        $this->addIndexIfNotExists('audit_logs', ['created_at'],           'idx_al_created_at');
        $this->addIndexIfNotExists('audit_logs', ['model_type', 'model_id'],'idx_al_model');

        // ── credit_notes ──────────────────────────────────────────────────────
        $this->addIndexIfNotExists('credit_notes', ['status', 'issued_at'], 'idx_cn_status_date');

        // ── purchase_orders (date column = ordered_at) ────────────────────────
        $this->addIndexIfNotExists('purchase_orders', ['status', 'ordered_at'], 'idx_po_status_date');

        // ── supplier_invoices (date column = received_at) ─────────────────────
        $this->addIndexIfNotExists('supplier_invoices', ['status', 'received_at'], 'idx_si_status_date');

        // ── delivery_notes ────────────────────────────────────────────────────
        $this->addIndexIfNotExists('delivery_notes', ['status', 'issued_at'], 'idx_dn_status_date');
    }

    public function down(): void
    {
        $drops = [
            'client_payments'   => 'idx_cp_status_date',
            'supplier_payments' => 'idx_sp_status_date',
            'orders'            => 'idx_orders_status_date',
            'product_stocks'    => 'idx_ps_quantity',
            'credit_notes'      => 'idx_cn_status_date',
            'purchase_orders'   => 'idx_po_status_date',
            'supplier_invoices' => 'idx_si_status_date',
            'delivery_notes'    => 'idx_dn_status_date',
        ];

        foreach ($drops as $table => $index) {
            if (Schema::hasTable($table)) {
                Schema::table($table, fn($t) => $t->dropIndex($index));
            }
        }

        Schema::table('quotes', function ($t) {
            $t->dropIndex('idx_quotes_status_date');
            $t->dropIndex('idx_quotes_expires_at');
        });
        Schema::table('stock_movements', function ($t) {
            $t->dropIndex('idx_sm_type_date');
            $t->dropIndex('idx_sm_product_warehouse_type');
        });
        Schema::table('audit_logs', function ($t) {
            $t->dropIndex('idx_al_created_at');
            $t->dropIndex('idx_al_model');
        });
    }

    /** Ajoute un index uniquement s'il n'existe pas déjà (idempotent). */
    private function addIndexIfNotExists(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($columns, $name) {
            $t->index($columns, $name);
        });
    }
};
