<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [PONT BANCAIRE] Lie un RIB société (company_bank_accounts) à un compte de
 * trésorerie opérationnel (cash_accounts type=banque) pour éviter la double
 * saisie : un RIB peut générer/pointer son compte trésorerie (rapprochement,
 * soldes, transactions).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_bank_accounts', function (Blueprint $table) {
            $table->foreignId('cash_account_id')->nullable()->after('company_id')
                  ->constrained('cash_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('company_bank_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_account_id');
        });
    }
};
