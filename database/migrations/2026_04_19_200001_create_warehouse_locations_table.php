<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->string('zone', 50)->nullable();
            $table->string('aisle', 20)->nullable();
            $table->string('rack', 20)->nullable();
            $table->string('level', 20)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['warehouse_id', 'code']);
        });

        Schema::table('product_stocks', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('warehouse_id')
                  ->constrained('warehouse_locations')->nullOnDelete();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('warehouse_id')
                  ->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('dest_location_id')->nullable()->after('to_warehouse_id')
                  ->constrained('warehouse_locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dest_location_id');
            $table->dropConstrainedForeignId('location_id');
        });
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });
        Schema::dropIfExists('warehouse_locations');
    }
};
