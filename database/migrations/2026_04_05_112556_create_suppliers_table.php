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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->enum('type', ['particulier', 'entreprise'])->default('entreprise');
            $table->string('name', 150);
            $table->string('phone', 20)->nullable();
            $table->string('phone2', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('website', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 80)->default('Burkina Faso');
            $table->string('ifu', 30)->nullable();
            $table->string('rccm', 50)->nullable();
            // Performance
            $table->decimal('rating', 3, 1)->nullable();      // note /5
            $table->unsignedSmallInteger('avg_delivery_days')->nullable();
            $table->decimal('balance', 15, 0)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
