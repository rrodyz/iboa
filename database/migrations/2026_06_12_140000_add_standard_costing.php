<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Coûts standard par unité sur la nomenclature
        Schema::table('bills_of_materials', function (Blueprint $table) {
            $table->decimal('std_material_cost', 15, 0)->default(0)->after('labor_per_unit');
            $table->decimal('std_labor_cost', 15, 0)->default(0)->after('std_material_cost');
            $table->decimal('std_machine_cost', 15, 0)->default(0)->after('std_labor_cost');
            $table->decimal('std_overhead_cost', 15, 0)->default(0)->after('std_machine_cost');
        });

        // Coût standard total + écart sur le coût de revient
        Schema::table('production_costs', function (Blueprint $table) {
            $table->decimal('standard_total', 15, 0)->default(0)->after('total_cost');
            $table->decimal('variance', 15, 0)->nullable()->after('standard_total'); // réel - standard (>0 défavorable)
        });
    }

    public function down(): void
    {
        Schema::table('bills_of_materials', function (Blueprint $table) {
            $table->dropColumn(['std_material_cost', 'std_labor_cost', 'std_machine_cost', 'std_overhead_cost']);
        });
        Schema::table('production_costs', function (Blueprint $table) {
            $table->dropColumn(['standard_total', 'variance']);
        });
    }
};
