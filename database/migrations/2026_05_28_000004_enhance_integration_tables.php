<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── api_integrations ─────────────────────────────────────────────────
        Schema::table('api_integrations', function (Blueprint $table) {
            $table->string('sandbox_base_url')->nullable()->after('base_url');
            $table->unsignedSmallInteger('timeout_seconds')->default(30)->after('sandbox_base_url');
            $table->boolean('notify_on_error')->default(true)->after('is_active');
            $table->unsignedInteger('error_count')->default(0)->after('last_error');
            $table->timestamp('last_error_at')->nullable()->after('error_count');
            $table->timestamp('last_success_at')->nullable()->after('last_error_at');
        });

        // ── api_logs ─────────────────────────────────────────────────────────
        Schema::table('api_logs', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('error_message');
            $table->string('user_agent', 255)->nullable()->after('ip_address');
            $table->unsignedBigInteger('job_id')->nullable()->after('user_agent')
                  ->comment('linked queue job ID for correlation');
        });

        // ── external_transactions ─────────────────────────────────────────────
        Schema::table('external_transactions', function (Blueprint $table) {
            $table->unsignedSmallInteger('retry_count')->default(0)->after('notes');
            $table->timestamp('last_retry_at')->nullable()->after('retry_count');
            $table->string('failure_reason')->nullable()->after('last_retry_at');
            $table->string('initiated_by')->nullable()->after('failure_reason')
                  ->comment('manual|webhook|job|simulator');
        });
    }

    public function down(): void
    {
        Schema::table('api_integrations', function (Blueprint $table) {
            $table->dropColumn([
                'sandbox_base_url', 'timeout_seconds', 'notify_on_error',
                'error_count', 'last_error_at', 'last_success_at',
            ]);
        });

        Schema::table('api_logs', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent', 'job_id']);
        });

        Schema::table('external_transactions', function (Blueprint $table) {
            $table->dropColumn(['retry_count', 'last_retry_at', 'failure_reason', 'initiated_by']);
        });
    }
};
