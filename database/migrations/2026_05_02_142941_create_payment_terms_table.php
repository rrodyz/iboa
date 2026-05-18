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
        Schema::create('payment_terms', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                          // "30 jours nets"
            $table->unsignedSmallInteger('days')->default(0);                // base delay in days
            $table->boolean('end_of_month')->default(false);                 // snap to end of month?
            $table->unsignedSmallInteger('additional_days')->default(0);     // extra days after end-of-month
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_terms');
    }
};
