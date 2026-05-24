<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [RH-PRO] Documents RH — pièces jointes par employé.
 * CNIB, contrats, diplômes, attestations, bulletins papier, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->enum('document_type', [
                'cnib',          // Carte nationale d'identité
                'passeport',     // Passeport
                'contrat',       // Contrat de travail signé
                'avenant',       // Avenant au contrat
                'diplome',       // Diplôme / certificat
                'attestation',   // Attestation de travail / de salaire
                'medical',       // Certificat médical
                'cnss',          // Carte CNSS
                'photo',         // Photo d'identité
                'autre',         // Autre document
            ])->default('autre');

            $table->string('label', 200)->comment('Intitulé affiché');
            $table->string('original_name', 255)->comment('Nom original du fichier');
            $table->string('file_path', 500)->comment('Chemin relatif dans storage');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable()->comment('Taille en octets');
            $table->date('document_date')->nullable()->comment('Date du document');
            $table->date('expires_at')->nullable()->comment('Date expiration (CNIB, passeport...)');
            $table->text('notes')->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
