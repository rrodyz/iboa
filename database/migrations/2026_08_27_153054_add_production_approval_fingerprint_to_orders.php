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
        Schema::table('orders', function (Blueprint $table) {
            // [P1-B] Empreinte du contrat financier au moment de l'approbation
            // gérant (client, mode de règlement, lignes, totaux). Comparée à
            // l'empreinte recalculée dans Order::hasValidProductionApproval() :
            // toute divergence rend l'approbation « stale » sans effacer
            // l'historique (approved_by/at/reason restent en base). NULL =
            // approbation posée avant ce correctif, jamais vérifiable → invalide
            // par construction fail-closed (aucune approbation existante n'est
            // réputée valide silencieusement).
            $table->string('production_approval_fingerprint', 64)->nullable()->after('production_approval_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * [Rollback destructeur en précision] Un retour arrière supprime la
     * colonne : toute empreinte enregistrée est perdue, et après re-migration
     * les approbations existantes redeviennent NULL (donc stale par
     * construction). Sans conséquence en développement (aucune donnée
     * métier réelle dans ce projet à ce jour) mais à documenter avant tout
     * usage en environnement porteur de données réelles.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('production_approval_fingerprint');
        });
    }
};
