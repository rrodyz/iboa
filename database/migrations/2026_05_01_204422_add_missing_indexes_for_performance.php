<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // [FIX-MAJEUR] invoices: index on order_id and delivery_note_id
        Schema::table('invoices', function (Blueprint $table) {
            if (!$this->hasIndex('invoices', 'invoices_order_id_index')) {
                $table->index('order_id');
            }
            if (!$this->hasIndex('invoices', 'invoices_delivery_note_id_index')) {
                $table->index('delivery_note_id');
            }
        });

        // [FIX-MAJEUR] client_payment_allocations: indexes on FKs
        Schema::table('client_payment_allocations', function (Blueprint $table) {
            if (!$this->hasIndex('client_payment_allocations', 'client_payment_allocations_invoice_id_index')) {
                $table->index('invoice_id');
            }
            if (!$this->hasIndex('client_payment_allocations', 'client_payment_allocations_client_payment_id_index')) {
                $table->index('client_payment_id');
            }
        });

        // [FIX-MINEUR] receptions: index on purchase_order_id
        Schema::table('receptions', function (Blueprint $table) {
            if (!$this->hasIndex('receptions', 'receptions_purchase_order_id_index')) {
                $table->index('purchase_order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasIndex('invoices', 'invoices_order_id_index'))
                $table->dropIndex('invoices_order_id_index');
            if (Schema::hasIndex('invoices', 'invoices_delivery_note_id_index'))
                $table->dropIndex('invoices_delivery_note_id_index');
        });
        Schema::table('client_payment_allocations', function (Blueprint $table) {
            if (Schema::hasIndex('client_payment_allocations', 'client_payment_allocations_invoice_id_index'))
                $table->dropIndex('client_payment_allocations_invoice_id_index');
            if (Schema::hasIndex('client_payment_allocations', 'client_payment_allocations_client_payment_id_index'))
                $table->dropIndex('client_payment_allocations_client_payment_id_index');
        });
        Schema::table('receptions', function (Blueprint $table) {
            if (Schema::hasIndex('receptions', 'receptions_purchase_order_id_index'))
                $table->dropIndex('receptions_purchase_order_id_index');
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return Schema::hasIndex($table, $index);
    }
};
