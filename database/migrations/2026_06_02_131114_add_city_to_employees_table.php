<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la colonne `city` (ville) manquante sur `employees`.
 *
 * Le contrôleur HR\EmployeeController (store/update), le $fillable du modèle
 * Employee et les formulaires create/edit référencent tous `city`, mais la
 * colonne n'avait jamais été créée → toute création/modification d'employé
 * échouait sur « SQLSTATE[42S22] Unknown column 'city' ».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'city')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('city', 80)->nullable()->after('address');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'city')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('city');
            });
        }
    }
};
