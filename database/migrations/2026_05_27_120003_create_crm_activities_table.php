<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // auteur

            // Polymorphe : peut être lié à un contact ou une opportunité (ou les deux)
            $table->foreignId('crm_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignId('crm_opportunity_id')->nullable()->constrained('crm_opportunities')->nullOnDelete();

            $table->enum('type', ['appel','email','rdv','note','tache'])->default('note');
            $table->string('subject');
            $table->text('description')->nullable();

            // Planification
            $table->datetime('due_at')->nullable(); // date/heure prévue
            $table->datetime('done_at')->nullable(); // date/heure réelle (null = à faire)

            $table->enum('priority', ['low','normal','high'])->default('normal');
            $table->boolean('is_done')->default(false);

            // Durée (en minutes) — utile pour appels/rdv
            $table->integer('duration_minutes')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'is_done']);
            $table->index(['crm_contact_id']);
            $table->index(['crm_opportunity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
    }
};
