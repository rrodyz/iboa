<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [ACHATS-PRO-APPROVAL] Workflow d'approbation des PO par seuil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('po_approval_thresholds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('min_amount', 18, 2)->default(0);
            $table->decimal('max_amount', 18, 2)->nullable();
            $table->string('required_role')->nullable();
            $table->string('required_permission')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'is_active']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('approval_status', ['non_requis','en_attente','approuve','rejete'])
                ->default('non_requis')->after('status');
            $table->timestamp('submitted_for_approval_at')->nullable()->after('approval_status');
            $table->foreignId('approved_by')->nullable()->after('submitted_for_approval_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->string('rejection_reason', 500)->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['approval_status', 'submitted_for_approval_at', 'approved_by', 'approved_at', 'rejection_reason']);
        });
        Schema::dropIfExists('po_approval_thresholds');
    }
};
