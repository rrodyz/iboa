<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fréquence de maintenance préventive sur la machine
        if (! Schema::hasColumn('production_machines', 'maintenance_frequency_days')) {
            Schema::table('production_machines', function (Blueprint $table) {
                $table->unsignedSmallInteger('maintenance_frequency_days')->nullable()->after('status');
            });
        }

        Schema::create('machine_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('machine_id')->constrained('production_machines')->cascadeOnDelete();
            $table->enum('type', ['preventive', 'corrective'])->default('preventive');
            $table->string('title', 200);
            $table->enum('status', ['planifie', 'en_cours', 'termine'])->default('planifie');
            $table->date('planned_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->decimal('downtime_minutes', 12, 2)->default(0);
            $table->decimal('cost', 15, 0)->default(0);
            $table->foreignId('operator_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'machine_id', 'status']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_maintenances');
        if (Schema::hasColumn('production_machines', 'maintenance_frequency_days')) {
            Schema::table('production_machines', function (Blueprint $table) {
                $table->dropColumn('maintenance_frequency_days');
            });
        }
    }
};
