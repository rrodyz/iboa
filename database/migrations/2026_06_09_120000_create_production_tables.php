<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [PRODUCTION] Module de fabrication de tôles bac.
 *
 * Réutilise l'existant : products (matières & produits finis), product_stocks,
 * StockService (mouvements), suppliers, clients, orders, units, employees.
 * Tables dédiées : suivi bobines (lot/poids), OF, BOM, conso, sorties, chutes, QC, coûts.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Machines de production ──────────────────────────────────────────────
        Schema::create('production_machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('code', 30);
            $table->string('name', 120);
            $table->enum('type', ['decoupe', 'profilage', 'mixte'])->default('mixte');
            $table->decimal('hourly_cost', 15, 0)->default(0); // coût machine / heure
            $table->enum('status', ['active', 'maintenance', 'arret'])->default('active');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        // ── Lignes de production ────────────────────────────────────────────────
        Schema::create('production_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('machine_id')->nullable()->constrained('production_machines')->nullOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        // ── Bobines (coils) — lot + poids, liée à un produit matière première ────
        Schema::create('coils', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete(); // matière 1re
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('reference', 50);
            $table->string('lot_number', 50)->nullable();
            $table->string('color', 50)->nullable();
            $table->decimal('thickness', 8, 2)->nullable();        // épaisseur mm
            $table->decimal('width', 8, 1)->nullable();            // largeur mm
            $table->decimal('initial_weight', 12, 2)->default(0);  // kg
            $table->decimal('remaining_weight', 12, 2)->default(0);// kg
            $table->decimal('estimated_length', 12, 2)->default(0);// m (longueur estimée)
            $table->decimal('purchase_price', 15, 0)->default(0);  // prix d'achat total
            $table->decimal('cost_per_kg', 15, 2)->default(0);     // coût au kg
            $table->date('received_at')->nullable();
            $table->enum('status', ['disponible', 'en_production', 'epuisee'])->default('disponible');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'status']);
            $table->index(['supplier_id']);
        });

        // ── Nomenclatures / Recettes (BOM) ──────────────────────────────────────
        Schema::create('bills_of_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete(); // produit fini
            $table->string('name', 150);
            $table->string('sheet_type', 80)->nullable();          // type tôle bac
            $table->decimal('thickness', 8, 2)->nullable();
            $table->decimal('coil_width', 8, 1)->nullable();       // largeur bobine mm
            $table->decimal('usable_width', 8, 1)->nullable();     // largeur utile mm
            $table->decimal('standard_waste_rate', 6, 2)->default(0); // % perte std
            $table->decimal('consumption_per_meter', 12, 4)->default(0); // kg/m
            $table->decimal('machine_time_per_unit', 10, 2)->default(0); // min
            $table->decimal('labor_per_unit', 10, 2)->default(0);  // min
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('bom_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_of_material_id')->constrained('bills_of_materials')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete(); // composant
            $table->string('label', 150)->nullable();
            $table->decimal('quantity_per_meter', 12, 4)->default(0);
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('waste_rate', 6, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['bill_of_material_id']);
        });

        // ── Ordres de fabrication ───────────────────────────────────────────────
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
            $table->string('number', 30)->unique();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();      // commande liée
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();  // produit fini
            $table->foreignId('bill_of_material_id')->nullable()->constrained('bills_of_materials')->nullOnDelete();
            $table->foreignId('production_line_id')->nullable()->constrained('production_lines')->nullOnDelete();
            $table->string('sheet_type', 80)->nullable();
            $table->decimal('thickness', 8, 2)->nullable();
            $table->string('color', 50)->nullable();
            $table->decimal('length', 12, 2)->nullable();          // longueur unitaire m
            $table->decimal('usable_width', 8, 1)->nullable();     // largeur utile mm
            $table->decimal('quantity_requested', 12, 2)->default(0);
            $table->decimal('quantity_produced', 12, 2)->default(0);
            $table->enum('status', ['brouillon', 'lance', 'en_cours', 'termine', 'annule'])->default('brouillon');
            $table->date('launched_at')->nullable();
            $table->date('finished_at')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status']);
            $table->index(['client_id']);
            $table->index(['order_id']);
        });

        Schema::create('production_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->decimal('length', 12, 2)->default(0);          // longueur d'une tôle (m)
            $table->decimal('quantity', 12, 2)->default(0);        // nombre de tôles
            $table->decimal('total_meters', 14, 2)->default(0);    // length × quantity
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('label', 150)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['production_order_id']);
        });

        // ── Consommations matière (bobine) ──────────────────────────────────────
        Schema::create('production_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('coil_id')->nullable()->constrained('coils')->nullOnDelete();
            $table->decimal('weight_consumed', 12, 2)->default(0); // kg
            $table->decimal('length_consumed', 14, 2)->default(0); // m
            $table->decimal('cost', 15, 0)->default(0);            // valeur consommée
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->date('consumed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['production_order_id']);
            $table->index(['coil_id']);
        });

        // ── Sorties / Produits finis ────────────────────────────────────────────
        Schema::create('production_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete(); // produit fini
            $table->decimal('length', 12, 2)->default(0);
            $table->string('color', 50)->nullable();
            $table->decimal('thickness', 8, 2)->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('total_meters', 14, 2)->default(0);
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->date('produced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['production_order_id']);
            $table->index(['product_id']);
        });

        // ── Pertes / Chutes / Rebuts ────────────────────────────────────────────
        Schema::create('production_wastes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('machine_id')->nullable()->constrained('production_machines')->nullOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('type', ['reutilisable', 'non_reutilisable', 'rebut'])->default('non_reutilisable');
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('weight', 12, 2)->default(0);          // kg
            $table->decimal('value', 15, 0)->default(0);           // valorisation perte
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['production_order_id']);
            $table->index(['type']);
        });

        // ── Contrôle qualité ────────────────────────────────────────────────────
        Schema::create('production_quality_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->boolean('thickness_ok')->default(true);
            $table->boolean('length_ok')->default(true);
            $table->boolean('color_ok')->default(true);
            $table->boolean('visual_ok')->default(true);
            $table->enum('status', ['conforme', 'non_conforme', 'a_reprendre'])->default('conforme');
            $table->string('reason', 500)->nullable();
            $table->decimal('rejected_quantity', 12, 2)->default(0);
            $table->foreignId('controller_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('controlled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['production_order_id']);
        });

        // ── Coûts de revient ────────────────────────────────────────────────────
        Schema::create('production_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->decimal('material_cost', 15, 0)->default(0);
            $table->decimal('labor_cost', 15, 0)->default(0);
            $table->decimal('machine_cost', 15, 0)->default(0);
            $table->decimal('overhead_cost', 15, 0)->default(0);
            $table->decimal('total_cost', 15, 0)->default(0);
            $table->decimal('cost_per_meter', 15, 2)->default(0);
            $table->decimal('cost_per_unit', 15, 2)->default(0);
            $table->decimal('margin', 15, 0)->nullable();          // si OF lié à commande
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['production_order_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'production_costs', 'production_quality_controls', 'production_wastes',
            'production_outputs', 'production_consumptions', 'production_order_lines',
            'production_orders', 'bom_lines', 'bills_of_materials', 'coils',
            'production_lines', 'production_machines',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
