<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Rapprochements bancaires ─────────────────────────────────────────
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();

            $table->string('number', 30)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('statement_date');

            $table->decimal('opening_balance',   15, 0)->default(0);
            $table->decimal('closing_balance',   15, 0)->default(0);
            $table->decimal('book_balance',      15, 0)->default(0);
            $table->decimal('difference',        15, 0)->default(0);

            $table->enum('status', ['brouillon', 'valide'])->default('brouillon');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'cash_account_id', 'period_end']);
        });

        // ── Lignes relevé bancaire ───────────────────────────────────────────
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained('bank_reconciliations')->cascadeOnDelete();
            $table->foreignId('journal_entry_line_id')->nullable()->constrained('journal_entry_lines')->nullOnDelete();

            $table->date('value_date');
            $table->string('label');
            $table->string('reference')->nullable();
            $table->decimal('debit',  15, 0)->default(0);
            $table->decimal('credit', 15, 0)->default(0);
            $table->boolean('is_matched')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['bank_reconciliation_id', 'is_matched']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_reconciliations');
    }
};
