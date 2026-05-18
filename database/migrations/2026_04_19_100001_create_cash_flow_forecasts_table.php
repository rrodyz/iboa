<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_flow_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            $table->string('number', 30)->unique();
            $table->string('label', 100);                                  // "Janvier 2026", "T1-2026"
            $table->enum('period_type', ['mensuel', 'trimestriel', 'annuel'])->default('mensuel');
            $table->date('period_start');
            $table->date('period_end');

            $table->decimal('opening_balance',          15, 0)->default(0);
            $table->decimal('total_inflows',            15, 0)->default(0);
            $table->decimal('total_outflows',           15, 0)->default(0);
            $table->decimal('net_flow',                 15, 0)->default(0);
            $table->decimal('closing_balance_forecast', 15, 0)->default(0);

            // Actuals (populated after the period)
            $table->decimal('actual_inflows',  15, 0)->default(0);
            $table->decimal('actual_outflows', 15, 0)->default(0);

            $table->enum('status', ['brouillon', 'valide'])->default('brouillon');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'period_start']);
        });

        Schema::create('cash_flow_forecast_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_id')->constrained('cash_flow_forecasts')->cascadeOnDelete();

            $table->enum('category', [
                // Inflows
                'encaissements_clients',
                'ventes_comptant',
                'autres_encaissements',
                // Outflows
                'achats_fournisseurs',
                'salaires',
                'charges_fiscales',
                'investissements',
                'remboursements_emprunts',
                'autres_charges',
            ]);
            $table->string('label', 200);
            $table->boolean('is_inflow')->default(true);

            $table->decimal('forecast_amount', 15, 0)->default(0);
            $table->decimal('actual_amount',   15, 0)->default(0);   // filled after period

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_flow_forecast_lines');
        Schema::dropIfExists('cash_flow_forecasts');
    }
};
