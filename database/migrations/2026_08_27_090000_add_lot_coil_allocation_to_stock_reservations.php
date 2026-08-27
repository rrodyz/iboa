<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [P1-D1] stock_reservations reste la SEULE source détaillée de réservation
 * (produit fini vente, matière première production) — cette migration lui
 * ajoute la granularité lot/bobine nécessaire à une allocation formelle
 * production, sans créer de second système parallèle.
 *
 * stock_lot_id / coil_id nullable : les réservations existantes (vente,
 * produit fini, matière non lotée) restent à granularité produit+dépôt,
 * comportement strictement inchangé.
 *
 * RESTRICT (pas nullOnDelete) : ni CoilController::destroy() ni
 * PurchaseOrderService::cancelReception() ne vérifient aujourd'hui qu'un lot/
 * bobine n'est pas activement alloué à un OF avant suppression (ils ne
 * vérifient que l'absence de CONSOMMATION). SET NULL romprait silencieusement
 * la traçabilité d'une allocation active ; RESTRICT force un rejet explicite
 * — les deux méthodes de suppression reçoivent en complément une garde
 * applicative dédiée (message métier, pas une erreur SQL brute).
 *
 * consumed_quantity : DECIMAL(14,2), même précision/échelle que `quantity`
 * (comparés/soustraits ensemble sur la même ligne) — pas celle de
 * stock_lots.quantity (12,4) ni coils.remaining_weight (12,2), sans rapport
 * avec cette colonne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->foreignId('stock_lot_id')->nullable()->after('warehouse_id')
                ->constrained('stock_lots')->restrictOnDelete();
            $table->foreignId('coil_id')->nullable()->after('stock_lot_id')
                ->constrained('coils')->restrictOnDelete();
            $table->decimal('consumed_quantity', 14, 2)->default(0)->after('quantity');

            $table->index('stock_lot_id');
            $table->index('coil_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_lot_id');
            $table->dropConstrainedForeignId('coil_id');
            $table->dropColumn('consumed_quantity');
        });
    }
};
