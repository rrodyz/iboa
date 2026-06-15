<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [TRESO] Dossiers de contentieux : escalade du recouvrement après échec des
 * relances. Suivi du stade (mise en demeure → huissier → tribunal) et de l'issue
 * (recouvré / irrécouvrable). Le passage en perte génère une écriture 6514/411.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('litigation_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('number', 30)->unique();
            $table->decimal('amount', 15, 0);
            $table->enum('stage', ['mise_en_demeure', 'huissier', 'avocat', 'tribunal', 'abandon'])->default('mise_en_demeure');
            $table->enum('status', ['ouvert', 'en_cours', 'suspendu', 'recouvre', 'irrecouvrable'])->default('ouvert');
            $table->date('opened_at');
            $table->date('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['client_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('litigation_cases');
    }
};
