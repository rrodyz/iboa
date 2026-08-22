<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('production_costs', function (Blueprint $table) {
            // Ventilation analytique de material_cost — n'affecte ni material_cost
            // ni total_cost, purement informationnel (CDC §6 récupérable/perdu).
            $table->integer('gross_material_cost')->nullable()->after('material_cost');
            $table->integer('waste_cost')->nullable()->after('gross_material_cost');
            $table->integer('useful_material_cost')->nullable()->after('waste_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_costs', function (Blueprint $table) {
            $table->dropColumn(['gross_material_cost', 'waste_cost', 'useful_material_cost']);
        });
    }
};
