<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Classes de comptes SYSCOHADA ─────────────────────────────────
        Schema::create('account_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedTinyInteger('number');           // 1-9
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });

        // ── 2. Plan comptable ────────────────────────────────────────────────
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('account_class_id')->constrained('account_classes')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();

            $table->string('code', 20)->index();
            $table->string('name');
            $table->enum('type', ['actif', 'passif', 'charge', 'produit', 'bilan', 'resultat'])->default('bilan');
            $table->boolean('is_detail')->default(true);     // leaf = postable
            $table->boolean('is_active')->default(true);

            $table->decimal('debit_balance', 20, 0)->default(0);
            $table->decimal('credit_balance', 20, 0)->default(0);

            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active', 'is_detail']);
        });

        // ── 3. Types de journaux ─────────────────────────────────────────────
        Schema::create('journal_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('name');
            $table->enum('type', ['achat', 'vente', 'banque', 'caisse', 'operations_diverses', 'a_nouveau'])->default('operations_diverses');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        // ── 4. En-têtes d'écritures ──────────────────────────────────────────
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('journal_type_id')->constrained('journal_types')->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();

            $table->string('number', 30)->unique();
            $table->date('entry_date');
            $table->date('value_date')->nullable();
            $table->string('reference')->nullable();     // document source (FA-00001, BL-00001…)
            $table->string('description');

            $table->enum('status', ['brouillon', 'valide', 'cloture'])->default('brouillon');

            $table->decimal('total_debit', 20, 0)->default(0);
            $table->decimal('total_credit', 20, 0)->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'journal_type_id', 'entry_date']);
            $table->index(['company_id', 'status']);
        });

        // ── 5. Lignes d'écritures ────────────────────────────────────────────
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();

            $table->string('label');
            $table->decimal('debit', 20, 0)->default(0);
            $table->decimal('credit', 20, 0)->default(0);
            $table->date('due_date')->nullable();
            $table->string('reconciliation_ref')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['journal_entry_id', 'sort_order']);
            $table->index(['account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('journal_types');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('account_classes');
    }
};
