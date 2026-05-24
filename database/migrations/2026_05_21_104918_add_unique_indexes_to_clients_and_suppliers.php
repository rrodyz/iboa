<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [ANTI-DUPLICATE] Indexes uniques sur les attributs structurants des tiers.
 *
 *   clients/suppliers : email, ifu (équivalent SIRET/NIF Burkina), rccm (registre commerce)
 *
 * Ces indexes sont la dernière ligne de défense contre les doublons : même si
 * un développeur oublie la validation côté Form Request, MySQL refusera l'insertion.
 * Les colonnes nullables acceptent plusieurs lignes NULL (comportement standard MySQL).
 *
 * Pré-traitement : tout doublon existant est nettoyé en suffixant un compteur
 * sur les copies (préserve les données mais lève la contrainte).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Nettoyer les éventuels doublons avant d'ajouter les indexes
        $this->dedupeColumn('clients',   'email');
        $this->dedupeColumn('clients',   'ifu');
        $this->dedupeColumn('clients',   'rccm');
        $this->dedupeColumn('suppliers', 'email');
        $this->dedupeColumn('suppliers', 'ifu');
        $this->dedupeColumn('suppliers', 'rccm');

        // 2) Ajouter les indexes uniques
        Schema::table('clients', function (Blueprint $table) {
            $table->unique('email',  'clients_email_unique');
            $table->unique('ifu',    'clients_ifu_unique');
            $table->unique('rccm',   'clients_rccm_unique');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->unique('email',  'suppliers_email_unique');
            $table->unique('ifu',    'suppliers_ifu_unique');
            $table->unique('rccm',   'suppliers_rccm_unique');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique('clients_email_unique');
            $table->dropUnique('clients_ifu_unique');
            $table->dropUnique('clients_rccm_unique');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique('suppliers_email_unique');
            $table->dropUnique('suppliers_ifu_unique');
            $table->dropUnique('suppliers_rccm_unique');
        });
    }

    /**
     * Suffixe les doublons existants pour laisser passer la contrainte unique
     * (préserve la donnée originale, marque les duplicates avec un n°).
     */
    private function dedupeColumn(string $table, string $column): void
    {
        if (!Schema::hasColumn($table, $column)) return;

        $duplicates = DB::table($table)
            ->select($column, DB::raw('COUNT(*) as n'))
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->pluck($column);

        foreach ($duplicates as $value) {
            $rows = DB::table($table)->where($column, $value)->orderBy('id')->get(['id']);
            // Garde la 1ère ligne intacte, suffixe les suivantes : "foo@bar.com (DUP-2)"
            foreach ($rows->skip(1) as $i => $row) {
                $newValue = $value . ' (DUP-' . ($i + 2) . ')';
                DB::table($table)->where('id', $row->id)->update([$column => $newValue]);
            }
        }
    }
};
