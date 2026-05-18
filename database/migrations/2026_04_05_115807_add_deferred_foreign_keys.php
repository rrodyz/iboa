<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // FK users -> companies (créé après companies)
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });

        // FK product_price_tiers -> clients
        Schema::table('product_price_tiers', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });

        // FK product_promotions -> clients
        Schema::table('product_promotions', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });

        // FK quotes -> orders (self-referencing converted_to_order_id)
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreign('converted_to_order_id')->references('id')->on('orders')->nullOnDelete();
        });

        // FK invoices -> parent_invoice (récurrence)
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('parent_invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });

        // FK client_payment_allocations -> client_payments
        Schema::table('client_payment_allocations', function (Blueprint $table) {
            $table->foreign('client_payment_id')->references('id')->on('client_payments')->cascadeOnDelete();
        });

        // FK supplier_payment_allocations -> supplier_payments
        Schema::table('supplier_payment_allocations', function (Blueprint $table) {
            $table->foreign('supplier_payment_id')->references('id')->on('supplier_payments')->cascadeOnDelete();
        });

        // FK supplier_contacts -> suppliers (même timestamp)
        Schema::table('supplier_contacts', function (Blueprint $table) {
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
        });

        // FK delivery_note_items -> delivery_notes (même timestamp)
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->foreign('delivery_note_id')->references('id')->on('delivery_notes')->cascadeOnDelete();
            $table->foreign('order_item_id')->references('id')->on('order_items')->nullOnDelete();
        });

        // FK credit_note_items -> credit_notes (même timestamp)
        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->foreign('credit_note_id')->references('id')->on('credit_notes')->cascadeOnDelete();
        });

        // FK supplier_invoice_items -> supplier_invoices (même timestamp)
        Schema::table('supplier_invoice_items', function (Blueprint $table) {
            $table->foreign('supplier_invoice_id')->references('id')->on('supplier_invoices')->cascadeOnDelete();
        });

        // FK inventory_items -> inventory_sessions (même timestamp)
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreign('inventory_session_id')->references('id')->on('inventory_sessions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropForeign(['inventory_session_id']);
        });
        Schema::table('supplier_invoice_items', function (Blueprint $table) {
            $table->dropForeign(['supplier_invoice_id']);
        });
        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->dropForeign(['credit_note_id']);
        });
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropForeign(['order_item_id']);
            $table->dropForeign(['delivery_note_id']);
        });
        Schema::table('supplier_contacts', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });
        Schema::table('supplier_payment_allocations', function (Blueprint $table) {
            $table->dropForeign(['supplier_payment_id']);
        });
        Schema::table('client_payment_allocations', function (Blueprint $table) {
            $table->dropForeign(['client_payment_id']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['parent_invoice_id']);
        });
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropForeign(['converted_to_order_id']);
        });
        Schema::table('product_promotions', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });
        Schema::table('product_price_tiers', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });
    }
};
