<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [TRESO] Seuil d'alerte de solde faible par compte de trésorerie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_accounts', function (Blueprint $table) {
            $table->decimal('min_balance', 15, 0)->default(0)->after('current_balance');
        });
    }

    public function down(): void
    {
        Schema::table('cash_accounts', function (Blueprint $table) {
            $table->dropColumn('min_balance');
        });
    }
};
