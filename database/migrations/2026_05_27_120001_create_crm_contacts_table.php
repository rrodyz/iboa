<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // responsable
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete(); // converti en client

            // Identification
            $table->enum('type', ['prospect', 'contact', 'partenaire'])->default('prospect');
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('job_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('BF');

            // Scoring & source
            $table->enum('source', ['direct','referral','web','social','event','other'])->default('direct');
            $table->integer('score')->default(0); // 0-100
            $table->enum('status', ['new','contacted','qualified','unqualified','converted','lost'])->default('new');

            // Secteur d'activité
            $table->string('sector')->nullable();

            // Notes libres
            $table->text('notes')->nullable();

            // Tag(s) : JSON array de strings
            $table->json('tags')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contacts');
    }
};
