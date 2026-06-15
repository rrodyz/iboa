<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [TRESO] Workflow de validation des décaissements par seuil de montant.
 * - Colonnes de workflow sur supplier_payments (soumission / validation / rejet).
 * - Table treasury_approval_thresholds : bande de montant → rôle approbateur requis.
 *
 * Les décaissements existants reçoivent validation_status = 'valide' (déjà comptabilisés).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            // 'valide' par défaut → les lignes existantes restent valides (non disruptif).
            $table->enum('validation_status', ['en_attente_validation', 'valide', 'rejete'])
                  ->default('valide')->after('status');
            $table->string('required_role', 50)->nullable()->after('validation_status');
            $table->json('pending_allocations')->nullable()->after('required_role');

            $table->foreignId('submitted_by')->nullable()->after('pending_allocations')->nullOnDelete()->constrained('users');
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->foreignId('validated_by')->nullable()->after('submitted_at')->nullOnDelete()->constrained('users');
            $table->timestamp('validated_at')->nullable()->after('validated_by');
            $table->foreignId('rejected_by')->nullable()->after('validated_at')->nullOnDelete()->constrained('users');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
        });

        Schema::create('treasury_approval_thresholds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('name', 100);
            $table->decimal('min_amount', 15, 0)->default(0);
            $table->decimal('max_amount', 15, 0)->nullable(); // null = pas de plafond
            $table->string('required_role', 50);
            $table->string('required_permission', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_approval_thresholds');

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('validated_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn([
                'validation_status', 'required_role', 'pending_allocations',
                'submitted_at', 'validated_at', 'rejected_at', 'rejection_reason',
            ]);
        });
    }
};
