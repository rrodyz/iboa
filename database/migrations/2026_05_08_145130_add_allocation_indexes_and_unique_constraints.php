<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [DB-PERF-S3] Fix two issues on allocation tables:
 *
 * 1. MISSING INDEX — client_payment_allocations.client_payment_id and
 *    supplier_payment_allocations.supplier_payment_id were declared as plain
 *    unsignedBigInteger (FK added later in a deferred migration).  InnoDB does
 *    NOT auto-create an index for a plain column + separate FK constraint added
 *    via ALTER TABLE — it only auto-creates one when the FK is declared inline
 *    with the column.  Every join from payment → allocations was doing a full
 *    table scan.
 *
 * 2. MISSING UNIQUE CONSTRAINT — the same invoice can be allocated twice within
 *    the same payment if the allocations array contains duplicates.  Adding a
 *    unique index prevents the double-allocation at the DB level regardless of
 *    application-layer checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── client_payment_allocations ────────────────────────────────────────

        Schema::table('client_payment_allocations', function (Blueprint $table) {
            // Index on FK column (lookup: all allocations for a payment)
            if (!$this->hasIndex('client_payment_allocations', 'client_payment_alloc_payment_id_index')) {
                $table->index('client_payment_id', 'client_payment_alloc_payment_id_index');
            }

            // Unique: one row per (payment, invoice) pair
            if (!$this->hasIndex('client_payment_allocations', 'client_payment_alloc_payment_invoice_unique')) {
                $table->unique(
                    ['client_payment_id', 'invoice_id'],
                    'client_payment_alloc_payment_invoice_unique'
                );
            }
        });

        // ── supplier_payment_allocations ──────────────────────────────────────

        Schema::table('supplier_payment_allocations', function (Blueprint $table) {
            // Index on FK column
            if (!$this->hasIndex('supplier_payment_allocations', 'supplier_payment_alloc_payment_id_index')) {
                $table->index('supplier_payment_id', 'supplier_payment_alloc_payment_id_index');
            }

            // Unique: one row per (payment, supplier_invoice) pair
            if (!$this->hasIndex('supplier_payment_allocations', 'supplier_payment_alloc_payment_invoice_unique')) {
                $table->unique(
                    ['supplier_payment_id', 'supplier_invoice_id'],
                    'supplier_payment_alloc_payment_invoice_unique'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_payment_allocations', function (Blueprint $table) {
            $table->dropUnique('client_payment_alloc_payment_invoice_unique');
            $table->dropIndex('client_payment_alloc_payment_id_index');
        });

        Schema::table('supplier_payment_allocations', function (Blueprint $table) {
            $table->dropUnique('supplier_payment_alloc_payment_invoice_unique');
            $table->dropIndex('supplier_payment_alloc_payment_id_index');
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
