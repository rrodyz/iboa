<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [ACHATS-PRO-RFQ] Demandes de devis multi-fournisseur.
 *
 *   rfqs            : la demande (en-tête + items uniques)
 *   rfq_items       : les lignes demandées (article + qté)
 *   rfq_suppliers   : les fournisseurs consultés (statut envoi/réponse)
 *   rfq_quotes      : la cotation reçue d'un fournisseur (totaux)
 *   rfq_quote_items : le prix unitaire proposé par fournisseur sur chaque ligne RFQ
 *
 * Workflow :
 *   brouillon → envoyee → recue → cloturee (un fournisseur retenu → PO)
 *                                          → annulee
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('number', 30)->unique();
            $table->string('title');
            $table->enum('status', ['brouillon','envoyee','recue','cloturee','annulee'])->default('brouillon');
            $table->date('deadline')->nullable();             // réponse attendue avant
            $table->text('notes')->nullable();
            $table->foreignId('awarded_quote_id')->nullable();   // FK ajoutée plus loin
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status']);
        });

        Schema::create('rfq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index('rfq_id');
        });

        Schema::create('rfq_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->enum('status', ['en_attente','envoyee','recue','declinee'])->default('en_attente');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('response_received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['rfq_id', 'supplier_id']);
        });

        Schema::create('rfq_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('rfq_supplier_id')->constrained('rfq_suppliers')->cascadeOnDelete();
            $table->string('supplier_reference', 100)->nullable();   // n° du devis fournisseur
            $table->date('valid_until')->nullable();
            $table->string('currency_code', 10)->default('XOF');
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->decimal('subtotal_ht', 18, 4)->default(0);
            $table->decimal('total_tax', 18, 4)->default(0);
            $table->decimal('total_ttc', 18, 4)->default(0);
            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_winner')->default(false);
            $table->timestamps();
            $table->index('rfq_id');
        });

        Schema::create('rfq_quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_quote_id')->constrained('rfq_quotes')->cascadeOnDelete();
            $table->foreignId('rfq_item_id')->constrained('rfq_items')->cascadeOnDelete();
            $table->decimal('unit_price', 18, 4);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total_ht', 18, 4);
            $table->decimal('line_total_ttc', 18, 4);
            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['rfq_quote_id', 'rfq_item_id']);
        });

        // FK différée : awarded_quote_id pointe vers rfq_quotes
        Schema::table('rfqs', function (Blueprint $table) {
            $table->foreign('awarded_quote_id')->references('id')->on('rfq_quotes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropForeign(['awarded_quote_id']);
        });
        Schema::dropIfExists('rfq_quote_items');
        Schema::dropIfExists('rfq_quotes');
        Schema::dropIfExists('rfq_suppliers');
        Schema::dropIfExists('rfq_items');
        Schema::dropIfExists('rfqs');
    }
};
