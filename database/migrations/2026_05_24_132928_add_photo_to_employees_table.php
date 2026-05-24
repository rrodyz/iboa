<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [RH-PRO] Ajout photo + champs complémentaires aux employés.
 * Colonnes additionnelles sans modifier les colonnes existantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Photo employé
            $table->string('photo_path', 500)->nullable()->after('matricule')
                  ->comment('Chemin relatif storage/photos');

            // Note: la colonne 'city' existe déjà, on ne la recrée pas

            // Informations complémentaires
            $table->string('emergency_contact_name', 100)->nullable()->after('phone')
                  ->comment('Contact urgence : nom');
            $table->string('emergency_contact_phone', 20)->nullable()->after('emergency_contact_name')
                  ->comment('Contact urgence : téléphone');
            $table->string('education_level', 50)->nullable()->after('category')
                  ->comment('Niveau d\'études : sans | bep | bac | licence | master | doctorat');
            $table->string('fonction', 100)->nullable()->after('job_title')
                  ->comment('Fonction précise dans le département');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'photo_path', 'emergency_contact_name', 'emergency_contact_phone',
                'education_level', 'fonction',
            ]);
        });
    }
};
