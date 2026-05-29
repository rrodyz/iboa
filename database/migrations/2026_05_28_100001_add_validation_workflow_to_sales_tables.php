<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute le statut "en_attente_validation" et les champs de soumission
 * à toutes les tables du module Ventes, plus les paramètres de validation
 * dans la table companies.
 *
 * N'utilise PAS ->change() sur les ENUM (non portable) — ALTER TABLE raw.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. quotes ──────────────────────────────────────────────────────────
        DB::statement("ALTER TABLE quotes MODIFY COLUMN status ENUM(
            'brouillon','en_attente_validation','envoye','accepte','refuse','expire','annule','converti','valide'
        ) NOT NULL DEFAULT 'brouillon'");

        Schema::table('quotes', function (Blueprint $table) {
            if (! Schema::hasColumn('quotes', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->after('validated_at')
                      ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('quotes', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            }
            if (! Schema::hasColumn('quotes', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('submitted_at')
                      ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('quotes', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
            if (! Schema::hasColumn('quotes', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }
        });

        // ── 2. orders ──────────────────────────────────────────────────────────
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
            'brouillon','en_attente_validation','confirme','en_preparation',
            'partiellement_livre','livre','facture','annule'
        ) NOT NULL DEFAULT 'brouillon'");

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->after('validated_at')
                      ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            }
            if (! Schema::hasColumn('orders', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('submitted_at')
                      ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
            if (! Schema::hasColumn('orders', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }
        });

        // ── 3. delivery_notes ──────────────────────────────────────────────────
        DB::statement("ALTER TABLE delivery_notes MODIFY COLUMN status ENUM(
            'brouillon','en_attente_validation','valide','livre','annule'
        ) NOT NULL DEFAULT 'brouillon'");

        Schema::table('delivery_notes', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_notes', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->after('validated_at')
                      ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('delivery_notes', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            }
            if (! Schema::hasColumn('delivery_notes', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('submitted_at')
                      ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('delivery_notes', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
            if (! Schema::hasColumn('delivery_notes', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }
        });

        // ── 4. invoices ────────────────────────────────────────────────────────
        DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM(
            'brouillon','en_attente_validation','emise','envoyee',
            'partiellement_payee','payee','en_retard','annulee'
        ) NOT NULL DEFAULT 'brouillon'");

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->after('validated_at')
                      ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('invoices', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            }
            if (! Schema::hasColumn('invoices', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('submitted_at')
                      ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('invoices', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
            if (! Schema::hasColumn('invoices', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }
        });

        // ── 5. credit_notes ────────────────────────────────────────────────────
        DB::statement("ALTER TABLE credit_notes MODIFY COLUMN status ENUM(
            'brouillon','en_attente_validation','valide','applique','annule'
        ) NOT NULL DEFAULT 'brouillon'");

        Schema::table('credit_notes', function (Blueprint $table) {
            if (! Schema::hasColumn('credit_notes', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->after('validated_at')
                      ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('credit_notes', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            }
            if (! Schema::hasColumn('credit_notes', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('submitted_at')
                      ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('credit_notes', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
            if (! Schema::hasColumn('credit_notes', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }
        });

        // ── 6. companies — paramètres de validation ───────────────────────────
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'validation_mode')) {
                $table->enum('validation_mode', ['simple', 'double'])->default('simple')->after('current_fiscal_year_id');
            }
            if (! Schema::hasColumn('companies', 'allow_self_validation')) {
                $table->boolean('allow_self_validation')->default(false)->after('validation_mode');
            }
        });
    }

    public function down(): void
    {
        // Restore original ENUM values
        DB::statement("ALTER TABLE quotes MODIFY COLUMN status ENUM(
            'brouillon','envoye','accepte','refuse','expire','annule','converti','valide'
        ) NOT NULL DEFAULT 'brouillon'");

        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
            'brouillon','confirme','en_preparation','partiellement_livre','livre','facture','annule'
        ) NOT NULL DEFAULT 'brouillon'");

        DB::statement("ALTER TABLE delivery_notes MODIFY COLUMN status ENUM(
            'brouillon','valide','livre','annule'
        ) NOT NULL DEFAULT 'brouillon'");

        DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM(
            'brouillon','emise','envoyee','partiellement_payee','payee','en_retard','annulee'
        ) NOT NULL DEFAULT 'brouillon'");

        DB::statement("ALTER TABLE credit_notes MODIFY COLUMN status ENUM(
            'brouillon','valide','applique','annule'
        ) NOT NULL DEFAULT 'brouillon'");

        Schema::table('quotes',         fn($t) => $t->dropColumn(['submitted_by','submitted_at','rejected_by','rejected_at','rejection_reason']));
        Schema::table('orders',         fn($t) => $t->dropColumn(['submitted_by','submitted_at','rejected_by','rejected_at','rejection_reason']));
        Schema::table('delivery_notes', fn($t) => $t->dropColumn(['submitted_by','submitted_at','rejected_by','rejected_at','rejection_reason']));
        Schema::table('invoices',       fn($t) => $t->dropColumn(['submitted_by','submitted_at','rejected_by','rejected_at','rejection_reason']));
        Schema::table('credit_notes',   fn($t) => $t->dropColumn(['submitted_by','submitted_at','rejected_by','rejected_at','rejection_reason']));

        Schema::table('companies', fn($t) => $t->dropColumn(['validation_mode', 'allow_self_validation']));
    }
};
