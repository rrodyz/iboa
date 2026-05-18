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
        Schema::create('document_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Apparence
            $table->string('primary_color', 7)->default('#1e40af');   // hex
            $table->string('font_family', 50)->default('DejaVu Sans');
            $table->string('page_size', 10)->default('A4');
            $table->string('orientation', 15)->default('portrait');
            // Contenu
            $table->boolean('show_logo')->default(true);
            $table->boolean('show_watermark')->default(false);
            $table->string('watermark_text', 50)->nullable();
            // Colonnes tableau produit (JSON: colonnes visibles et leur ordre)
            $table->json('product_columns')->nullable();
            // Pied de page
            $table->text('footer_text')->nullable();
            $table->text('terms_conditions')->nullable();    // CGV
            $table->string('signature_name', 100)->nullable();
            $table->string('signature_title', 100)->nullable();
            $table->string('signature_image')->nullable();   // chemin fichier
            $table->string('stamp_image')->nullable();       // cachet
            // Paramètres par type de document (JSON: show_bank_details, etc.)
            $table->json('quote_settings')->nullable();
            $table->json('invoice_settings')->nullable();
            $table->json('delivery_settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_settings');
    }
};
