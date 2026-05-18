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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();

            $table->string('name', 80);

            // ex: especes, virement, cheque, orange_money, moov_money, autre
            $table->string('code', 30)->unique();

            $table->enum('type', ['especes', 'virement', 'cheque', 'mobile_money', 'autre'])
                  ->default('autre');

            // Pour mobile_money: "Orange Money", "Moov Money"
            $table->string('provider', 50)->nullable();

            $table->boolean('is_mobile_money')->default(false);

            // Requiert un n° de référence (chèque, virement…)
            $table->boolean('requires_reference')->default(false);

            $table->string('icon', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
