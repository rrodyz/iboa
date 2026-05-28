<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [AUDIT-ERP-A] Lien traçabilité document ↔ écriture comptable.
 *
 * Ajoute journal_entry_id (nullable, nullOnDelete) sur :
 *   - invoices           → écriture générée lors de validate()
 *   - supplier_invoices  → écriture générée lors de validate()
 *   - client_payments    → écriture générée lors de create()
 *   - supplier_payments  → écriture générée lors de create()
 *
 * Ajoute quote_id sur invoices (la facture peut remonter au devis source).
 *
 * Toutes les FK sont nullable + nullOnDelete → aucune donnée existante
 * n'est affectée. Migration réversible (down() retire les colonnes).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── invoices ──────────────────────────────────────────────────────────
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'journal_entry_id')) {
                $table->foreignId('journal_entry_id')
                      ->nullable()
                      ->after('parent_invoice_id')
                      ->constrained('journal_entries')
                      ->nullOnDelete();
            }
            if (! Schema::hasColumn('invoices', 'quote_id')) {
                $table->foreignId('quote_id')
                      ->nullable()
                      ->after('order_id')
                      ->constrained('quotes')
                      ->nullOnDelete();
            }
        });

        // ── supplier_invoices ─────────────────────────────────────────────────
        Schema::table('supplier_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('supplier_invoices', 'journal_entry_id')) {
                $table->foreignId('journal_entry_id')
                      ->nullable()
                      ->after('validated_by')
                      ->constrained('journal_entries')
                      ->nullOnDelete();
            }
        });

        // ── client_payments ────────────────────────────────────────────────────
        Schema::table('client_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('client_payments', 'journal_entry_id')) {
                $table->foreignId('journal_entry_id')
                      ->nullable()
                      ->after('created_by')
                      ->constrained('journal_entries')
                      ->nullOnDelete();
            }
        });

        // ── supplier_payments ──────────────────────────────────────────────────
        Schema::table('supplier_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('supplier_payments', 'journal_entry_id')) {
                $table->foreignId('journal_entry_id')
                      ->nullable()
                      ->after('created_by')
                      ->constrained('journal_entries')
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeignIfExists(['journal_entry_id']);
            $table->dropForeignIfExists(['quote_id']);
            if (Schema::hasColumn('invoices', 'journal_entry_id')) $table->dropColumn('journal_entry_id');
            if (Schema::hasColumn('invoices', 'quote_id'))          $table->dropColumn('quote_id');
        });
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropForeignIfExists(['journal_entry_id']);
            if (Schema::hasColumn('supplier_invoices', 'journal_entry_id')) $table->dropColumn('journal_entry_id');
        });
        Schema::table('client_payments', function (Blueprint $table) {
            $table->dropForeignIfExists(['journal_entry_id']);
            if (Schema::hasColumn('client_payments', 'journal_entry_id')) $table->dropColumn('journal_entry_id');
        });
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropForeignIfExists(['journal_entry_id']);
            if (Schema::hasColumn('supplier_payments', 'journal_entry_id')) $table->dropColumn('journal_entry_id');
        });
    }
};
