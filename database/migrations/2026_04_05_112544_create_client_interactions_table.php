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
        Schema::create('client_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['appel', 'email', 'visite', 'reunion', 'relance', 'autre']);
            $table->timestamp('occurred_at');
            $table->string('subject', 200)->nullable();
            $table->text('notes');
            $table->enum('outcome', ['positif', 'neutre', 'negatif'])->nullable();
            $table->timestamp('followup_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_interactions');
    }
};
