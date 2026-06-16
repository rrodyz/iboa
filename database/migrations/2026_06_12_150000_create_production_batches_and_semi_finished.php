<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drapeau produit semi-fini (composant fabriqué, BOM multi-niveaux)
        if (! Schema::hasColumn('products', 'is_semi_finished')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('is_semi_finished')->default(false)->after('is_stockable');
            });
        }

        // Lots de fabrication (traçabilité)
        Schema::create('production_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('batch_number', 40);
            $table->decimal('quantity', 14, 2)->default(0);
            $table->enum('status', ['en_cours', 'cloture'])->default('en_cours');
            $table->date('produced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'batch_number']);
            $table->index('production_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_batches');
        if (Schema::hasColumn('products', 'is_semi_finished')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('is_semi_finished');
            });
        }
    }
};
