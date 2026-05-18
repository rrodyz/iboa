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
        Schema::create('inventory_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                  ->constrained('companies');

            $table->foreignId('warehouse_id')
                  ->nullable()
                  ->nullOnDelete()
                  ->constrained('warehouses');

            $table->string('number', 30)->unique();

            $table->enum('type', ['tournant', 'annuel', 'complet'])->default('complet');

            $table->enum('status', ['ouvert', 'en_cours', 'valide', 'annule'])
                  ->default('ouvert');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('validated_at')->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                  ->nullable()
                  ->nullOnDelete()
                  ->constrained('users');

            $table->foreignId('validated_by')
                  ->nullable()
                  ->nullOnDelete()
                  ->constrained('users');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_sessions');
    }
};
