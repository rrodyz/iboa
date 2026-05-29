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
        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id'); // FK différée — voir add_rh_deferred_foreign_keys
            $table->enum('type', ['CDI', 'CDD', 'stage', 'consultant'])->default('CDI');
            $table->date('start_date');
            $table->date('end_date')->nullable()->comment('Null = CDI');
            $table->unsignedBigInteger('base_salary')->comment('Salaire de base en FCFA');
            $table->enum('status', ['actif', 'termine', 'resilie'])->default('actif');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
    }
};
