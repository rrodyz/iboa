<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            // Parts de base par statut matrimonial (configurables — varient selon législation)
            $table->decimal('parts_base_single',  4, 2)->default(1.00)->after('parts_per_child')
                  ->comment('Parts de base — Célibataire / Divorcé(e)');
            $table->decimal('parts_base_married', 4, 2)->default(2.00)->after('parts_base_single')
                  ->comment('Parts de base — Marié(e)');
            $table->decimal('parts_base_widowed', 4, 2)->default(1.50)->after('parts_base_married')
                  ->comment('Parts de base — Veuf / Veuve');
        });

        DB::table('payroll_settings')->update([
            'parts_base_single'  => 1.00,
            'parts_base_married' => 2.00,
            'parts_base_widowed' => 1.50,
        ]);
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn(['parts_base_single', 'parts_base_married', 'parts_base_widowed']);
        });
    }
};
