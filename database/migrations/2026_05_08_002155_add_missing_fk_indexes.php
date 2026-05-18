<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [DB-PERF] Add missing indexes on FK columns that were declared as plain
 * unsignedBigInteger without an accompanying index, causing full table scans
 * on every JOIN / WHERE involving these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        // delivery_note_items.delivery_note_id
        Schema::table('delivery_note_items', function (Blueprint $table) {
            if (!$this->hasIndex('delivery_note_items', 'delivery_note_items_delivery_note_id_index')) {
                $table->index('delivery_note_id');
            }
        });

        // credit_note_items.credit_note_id
        Schema::table('credit_note_items', function (Blueprint $table) {
            if (!$this->hasIndex('credit_note_items', 'credit_note_items_credit_note_id_index')) {
                $table->index('credit_note_id');
            }
        });

        // supplier_invoice_items.supplier_invoice_id
        Schema::table('supplier_invoice_items', function (Blueprint $table) {
            if (!$this->hasIndex('supplier_invoice_items', 'supplier_invoice_items_supplier_invoice_id_index')) {
                $table->index('supplier_invoice_id');
            }
        });

        // inventory_items.inventory_session_id
        Schema::table('inventory_items', function (Blueprint $table) {
            if (!$this->hasIndex('inventory_items', 'inventory_items_inventory_session_id_index')) {
                $table->index('inventory_session_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropIndex(['delivery_note_id']);
        });

        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->dropIndex(['credit_note_id']);
        });

        Schema::table('supplier_invoice_items', function (Blueprint $table) {
            $table->dropIndex(['supplier_invoice_id']);
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropIndex(['inventory_session_id']);
        });
    }

    /**
     * Check whether an index already exists (prevents duplicate index errors).
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = \Illuminate\Support\Facades\DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        );
        return count($indexes) > 0;
    }
};
