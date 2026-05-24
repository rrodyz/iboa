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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();

            // Identification
            $table->string('matricule', 30)->unique();
            $table->string('last_name', 100);
            $table->string('first_name', 100);
            $table->enum('gender', ['M', 'F'])->default('M');
            $table->date('birth_date')->nullable();
            $table->string('nationality', 60)->default('Burkinabè');

            // Documents officiels
            $table->string('cin_number', 30)->nullable()->comment('Carte d\'identité nationale');
            $table->string('cnss_number', 30)->nullable()->comment('Numéro immatriculation CNSS');

            // Contact
            $table->string('email', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();

            // Poste
            $table->string('job_title', 100)->nullable();
            $table->enum('category', ['cadre', 'agent_maitrise', 'employe', 'ouvrier'])->default('employe');
            $table->date('hiring_date')->nullable();
            $table->enum('status', ['actif', 'suspendu', 'licencie', 'demissionne'])->default('actif');

            // Situation familiale (pour IUTS)
            $table->enum('family_status', ['celibataire', 'marie', 'veuf', 'divorce'])->default('celibataire');
            $table->unsignedTinyInteger('nb_children')->default(0)->comment('Enfants à charge');

            // Banque
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account', 50)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
