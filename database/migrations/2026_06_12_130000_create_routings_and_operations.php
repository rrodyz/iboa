<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gamme opératoire (rattachée à une nomenclature)
        Schema::create('routings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('bill_of_material_id')->nullable()->constrained('bills_of_materials')->nullOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
            $table->index('bill_of_material_id');
        });

        // Opérations de la gamme
        Schema::create('routing_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routing_id')->constrained('routings')->cascadeOnDelete();
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
            $table->unsignedSmallInteger('sequence')->default(10);
            $table->string('name', 150);
            $table->decimal('setup_minutes', 8, 2)->default(0);          // temps de réglage
            $table->decimal('run_minutes_per_unit', 8, 2)->default(0);   // temps unitaire
            $table->timestamps();
            $table->index('routing_id');
        });

        // Work Orders : opérations d'un OF (instanciées depuis la gamme)
        Schema::create('production_order_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('routing_operation_id')->nullable()->constrained('routing_operations')->nullOnDelete();
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedSmallInteger('sequence')->default(10);
            $table->string('name', 150);
            $table->decimal('planned_minutes', 10, 2)->default(0);
            $table->decimal('real_minutes', 10, 2)->default(0);
            $table->enum('status', ['pending', 'in_progress', 'done'])->default('pending');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['production_order_id', 'sequence']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_operations');
        Schema::dropIfExists('routing_operations');
        Schema::dropIfExists('routings');
    }
};
