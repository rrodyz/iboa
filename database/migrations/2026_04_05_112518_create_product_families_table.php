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
        Schema::create('product_families', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('product_families')->nullOnDelete();
            $table->string('name', 100);
            $table->string('code', 20)->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->unsignedTinyInteger('depth')->default(0); // 0=famille, 1=sous-famille
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_families');
    }
};
