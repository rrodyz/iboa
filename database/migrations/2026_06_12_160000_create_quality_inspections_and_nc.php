<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contrôles qualité transverses (réception / en-cours / produit fini)
        Schema::create('quality_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->enum('type', ['reception', 'en_cours', 'produit_fini'])->default('reception');
            $table->foreignId('reception_id')->nullable()->constrained('receptions')->nullOnDelete();
            $table->foreignId('production_order_id')->nullable()->constrained('production_orders')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('controller_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('reference', 40);
            $table->date('inspected_at')->nullable();
            $table->enum('status', ['conforme', 'non_conforme', 'partiel'])->default('conforme');
            $table->decimal('quantity_checked', 14, 2)->default(0);
            $table->decimal('quantity_rejected', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'type', 'status']);
        });

        // Non-conformités + action corrective (CAPA)
        Schema::create('non_conformities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('quality_inspection_id')->nullable()->constrained('quality_inspections')->nullOnDelete();
            $table->string('reference', 40);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('severity', ['mineure', 'majeure', 'critique'])->default('mineure');
            $table->enum('status', ['ouverte', 'en_cours', 'cloturee'])->default('ouverte');
            $table->text('corrective_action')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->date('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'status', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_conformities');
        Schema::dropIfExists('quality_inspections');
    }
};
