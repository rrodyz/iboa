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
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            // Type : devis, commande, bon_livraison, facture, avoir,
            //        commande_achat, facture_fournisseur
            $table->string('document_type', 30);
            $table->string('prefix', 20)->nullable();        // ex: FA-, DEV-
            $table->string('suffix', 20)->nullable();
            $table->unsignedTinyInteger('padding')->default(5);   // 00001
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'fiscal_year_id', 'document_type'], 'doc_seq_company_fy_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
