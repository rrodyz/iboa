<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les champs d'identification RH manquants sur payroll_settings :
 *   - cnss_affiliation : N° d'affiliation CNSS de l'employeur (ex. 213762A)
 *   - phone            : Téléphone affiché sur le bulletin de paie
 *   - address_bulletin : Adresse affichée sur le bulletin (peut différer de l'adresse légale)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_settings', 'cnss_affiliation')) {
                $table->string('cnss_affiliation', 30)->nullable()->after('country_code')
                      ->comment('N° affiliation CNSS employeur — affiché sur le bulletin');
            }
            if (! Schema::hasColumn('payroll_settings', 'phone')) {
                $table->string('phone', 30)->nullable()->after('cnss_affiliation')
                      ->comment('Téléphone affiché sur le bulletin');
            }
            if (! Schema::hasColumn('payroll_settings', 'address_bulletin')) {
                $table->string('address_bulletin', 200)->nullable()->after('phone')
                      ->comment('Adresse affichée sur le bulletin de paie');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $cols = ['cnss_affiliation', 'phone', 'address_bulletin'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('payroll_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
