<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [RH-PRO] Lien entre un employé et son compte utilisateur (portail self-service).
 * Nullable : tous les employés n'ont pas forcément un compte utilisateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('user_id')
                  ->nullable()
                  ->after('created_by')
                  ->constrained()
                  ->nullOnDelete()
                  ->comment('Compte utilisateur lié (portail employé)');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\User::class);
        });
    }
};
