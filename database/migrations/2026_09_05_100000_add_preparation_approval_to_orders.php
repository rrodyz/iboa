<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [R4.4 / R4.5 / R4.18] Approbation hiérarchique du passage en bon de préparation.
 *
 * Une commande à crédit ne doit plus générer automatiquement son bon de
 * préparation : elle porte une demande d'approbation que seul un responsable
 * (permission `bon_preparations.validate`) peut accorder. Le dépassement de
 * plafond emprunte le même chemin, avec le contexte financier figé au moment de
 * la demande (plafond, encours, montant, projeté, dépassement) — sans quoi une
 * décision prise plus tard ne serait plus justifiable.
 *
 * Additive uniquement : aucune donnée existante n'est convertie. Les commandes
 * déjà en cours restent sans demande (`preparation_approval_status` NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('preparation_approval_status', 20)->nullable()->after('production_approval_fingerprint');
            $table->foreignId('preparation_requested_by')->nullable()->after('preparation_approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('preparation_requested_at')->nullable()->after('preparation_requested_by');
            $table->foreignId('preparation_approved_by')->nullable()->after('preparation_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('preparation_approved_at')->nullable()->after('preparation_approved_by');
            $table->text('preparation_approval_reason')->nullable()->after('preparation_approved_at');
            $table->json('preparation_approval_context')->nullable()->after('preparation_approval_reason');

            // [R4.5] Dépassement d'encours : le blocage de soumission n'est plus
            // terminal. La commande porte une demande d'approbation exceptionnelle
            // avec l'empreinte du contrat financier au moment de la décision — une
            // commande retouchée après coup perd sa dérogation (fail-closed).
            $table->string('credit_overrun_status', 20)->nullable()->after('preparation_approval_context');
            $table->foreignId('credit_overrun_requested_by')->nullable()->after('credit_overrun_status')->constrained('users')->nullOnDelete();
            $table->timestamp('credit_overrun_requested_at')->nullable()->after('credit_overrun_requested_by');
            $table->foreignId('credit_overrun_approved_by')->nullable()->after('credit_overrun_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('credit_overrun_approved_at')->nullable()->after('credit_overrun_approved_by');
            $table->text('credit_overrun_reason')->nullable()->after('credit_overrun_approved_at');
            $table->json('credit_overrun_context')->nullable()->after('credit_overrun_reason');
            $table->string('credit_overrun_fingerprint', 64)->nullable()->after('credit_overrun_context');

            $table->index(['preparation_approval_status'], 'orders_preparation_approval_status_index');
            $table->index(['credit_overrun_status'], 'orders_credit_overrun_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_preparation_approval_status_index');
            $table->dropIndex('orders_credit_overrun_status_index');
            $table->dropConstrainedForeignId('preparation_requested_by');
            $table->dropConstrainedForeignId('preparation_approved_by');
            $table->dropConstrainedForeignId('credit_overrun_requested_by');
            $table->dropConstrainedForeignId('credit_overrun_approved_by');
            $table->dropColumn([
                'preparation_approval_status',
                'preparation_requested_at',
                'preparation_approved_at',
                'preparation_approval_reason',
                'preparation_approval_context',
                'credit_overrun_status',
                'credit_overrun_requested_at',
                'credit_overrun_approved_at',
                'credit_overrun_reason',
                'credit_overrun_context',
                'credit_overrun_fingerprint',
            ]);
        });
    }
};
