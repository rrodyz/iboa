<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_rates', function (Blueprint $table) {
            // Compte de TVA collectée par taux (sinon fallback sur 4431 global)
            $table->foreignId('collected_account_id')->nullable()->after('type')
                  ->constrained('accounts')->nullOnDelete()
                  ->comment('Compte GL de TVA collectée (4431x)');

            // Compte de TVA déductible par taux (sinon fallback sur 4455 global)
            $table->foreignId('deductible_account_id')->nullable()->after('collected_account_id')
                  ->constrained('accounts')->nullOnDelete()
                  ->comment('Compte GL de TVA déductible (4455x)');
        });
    }

    public function down(): void
    {
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->dropForeign(['collected_account_id']);
            $table->dropForeign(['deductible_account_id']);
            $table->dropColumn(['collected_account_id', 'deductible_account_id']);
        });
    }
};
