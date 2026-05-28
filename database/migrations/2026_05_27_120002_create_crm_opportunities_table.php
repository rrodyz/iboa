<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // commercial responsable

            $table->string('title');
            $table->decimal('amount', 15, 2)->default(0); // montant estimé FCFA
            $table->integer('probability')->default(25); // % chance de gagner
            $table->date('expected_close')->nullable();

            $table->enum('stage', [
                'prospection',   // 1 - Premier contact
                'qualification', // 2 - Intérêt confirmé
                'proposition',   // 3 - Offre envoyée
                'negociation',   // 4 - En négociation
                'gagne',         // 5 - Gagné ✓
                'perdu',         // 6 - Perdu ✗
            ])->default('prospection');

            $table->string('lost_reason')->nullable(); // raison perte (si perdu)
            $table->string('product_service')->nullable(); // produit/service concerné
            $table->text('notes')->nullable();

            $table->integer('sort_order')->default(0); // ordre dans le Kanban

            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'stage']);
            $table->index(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunities');
    }
};
