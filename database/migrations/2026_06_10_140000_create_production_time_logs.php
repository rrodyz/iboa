<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->decimal('hours', 8, 2)->default(0);
            $table->decimal('hourly_cost', 15, 2)->default(0);
            $table->decimal('labor_cost', 15, 0)->default(0);   // hours × hourly_cost
            $table->boolean('is_overtime')->default(false);
            $table->date('entry_date')->nullable();
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['production_order_id']);
            $table->index(['employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_time_logs');
    }
};
