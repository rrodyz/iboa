<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [STOCK-PRO] Transferts inter-dépôts — workflow standalone à 2 étapes.
 *
 *  brouillon → en_transit  (« Expédier »  : décrémente le dépôt source)
 *  en_transit → recu       (« Recevoir »  : incrémente le dépôt destination)
 *  brouillon / en_transit → annule
 *
 * Ces tables remplacent les transferts ad-hoc passés via stock_movements
 * en leur donnant un cycle de vie propre, un numéro de référence et une
 * traçabilité expéditeur / réceptionnaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('number', 30)->unique();

            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();

            $table->enum('status', ['brouillon', 'en_transit', 'recu', 'annule'])->default('brouillon');

            $table->date('transfer_date');           // date prévue
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['from_warehouse_id', 'status']);
            $table->index(['to_warehouse_id', 'status']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);

            // Suivi qté effectivement reçue (utile si écart d'inventaire)
            $table->decimal('received_quantity', 18, 4)->nullable();

            $table->decimal('unit_cost', 18, 4)->nullable();      // CMP à l'expédition
            $table->string('lot_number')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('label', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index('stock_transfer_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
    }
};
