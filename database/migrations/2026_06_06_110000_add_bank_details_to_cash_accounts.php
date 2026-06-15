<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [TRESO] Coordonnées bancaires sur les comptes de type "banque" :
 * banque, agence, numéro de compte (RIB), IBAN, code SWIFT/BIC.
 * Champs optionnels — non disruptif pour les caisses/mobile money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_accounts', function (Blueprint $table) {
            $table->string('bank_name', 150)->nullable()->after('type');
            $table->string('bank_branch', 150)->nullable()->after('bank_name');   // agence
            $table->string('account_number', 50)->nullable()->after('bank_branch'); // RIB
            $table->string('iban', 34)->nullable()->after('account_number');
            $table->string('swift_bic', 11)->nullable()->after('iban');
        });
    }

    public function down(): void
    {
        Schema::table('cash_accounts', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_branch', 'account_number', 'iban', 'swift_bic']);
        });
    }
};
