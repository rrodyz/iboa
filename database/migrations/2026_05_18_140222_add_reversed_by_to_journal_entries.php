<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add reversal tracking columns so a journal entry knows whether it has been
 * contre-passée, and a reversal entry knows which original it cancels.
 *
 * Used by AccountingService::reverseEntry() to enforce idempotence — a single
 * validated entry can only be contre-passée once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('reversed_by_entry_id')->nullable()->after('validated_at')
                ->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('reverses_entry_id')->nullable()->after('reversed_by_entry_id')
                ->constrained('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['reversed_by_entry_id']);
            $table->dropForeign(['reverses_entry_id']);
            $table->dropColumn(['reversed_by_entry_id', 'reverses_entry_id']);
        });
    }
};
