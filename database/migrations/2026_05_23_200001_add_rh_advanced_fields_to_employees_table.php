<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Colonnes déjà existantes : city, nationality, address, bank_name, bank_account, hiring_date
            $table->string('birth_place', 100)->nullable()->after('birth_date');
            $table->string('payment_mode', 20)->default('virement')->after('bank_account')
                  ->comment('virement, especes, cheque');
            $table->string('bank_code', 20)->nullable()->after('payment_mode');
            $table->string('bank_branch', 20)->nullable()->after('bank_code');
            $table->string('bank_account_number', 30)->nullable()->after('bank_branch');
            $table->string('bank_rib_key', 4)->nullable()->after('bank_account_number');
            $table->date('leave_date')->nullable()->after('hiring_date')
                  ->comment('Date de fin de contrat / sortie');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['birth_place','payment_mode','bank_code','bank_branch','bank_account_number','bank_rib_key','leave_date']);
        });
    }
};
