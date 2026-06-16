<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('machine_id')->nullable()->constrained('production_machines')->nullOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->decimal('capacity_hours_per_day', 6, 2)->default(8);   // capacité journalière (h)
            $table->decimal('cost_per_hour', 15, 2)->default(0);           // coût horaire centre
            $table->decimal('efficiency_rate', 6, 2)->default(100);        // rendement % (OEE théorique)
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_centers');
    }
};
