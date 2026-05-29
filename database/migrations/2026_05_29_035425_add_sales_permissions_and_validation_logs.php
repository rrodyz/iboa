<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nettoyage du workflow de validation Ventes :
 *
 * 1. Renomme les permissions commercial.* → sales.* dans Spatie Laravel-Permission.
 *    Les assignments role_has_permissions sont liés par ID → aucune donnée perdue.
 *
 * 2. Crée la table sales_validation_logs (alias propre de commercial_validations).
 *    La table commercial_validations reste intacte pour la rétro-compatibilité.
 */
return new class extends Migration
{
    /** Mapping old_name => new_name pour les permissions commerciales. */
    private const RENAMES = [
        'commercial.create'    => 'sales.create',
        'commercial.submit'    => 'sales.submit',
        'commercial.validate'  => 'sales.validate',
        'commercial.reject'    => 'sales.reject',
        'commercial.cancel'    => 'sales.cancel',
        'commercial.transform' => 'sales.transform',
        'commercial.view_all'  => 'sales.view_all',
    ];

    public function up(): void
    {
        // ── 1. Renommer les permissions Spatie ────────────────────────────────
        foreach (self::RENAMES as $old => $new) {
            $exists = DB::table('permissions')->where('name', $new)->exists();
            if (! $exists) {
                $oldId = DB::table('permissions')->where('name', $old)->value('id');
                if ($oldId) {
                    // Renomme directement — les IDs dans role_has_permissions
                    // sont préservés car ils référencent le même enregistrement.
                    DB::table('permissions')
                        ->where('id', $oldId)
                        ->update(['name' => $new, 'updated_at' => now()]);
                } else {
                    // Permission absente → on la crée (installation fraîche).
                    DB::table('permissions')->insert([
                        'name'       => $new,
                        'guard_name' => 'web',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Invalider le cache Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── 2. Créer sales_validation_logs ────────────────────────────────────
        if (! Schema::hasTable('sales_validation_logs')) {
            Schema::create('sales_validation_logs', function (Blueprint $table) {
                $table->id();
                $table->string('document_type', 50);
                $table->unsignedBigInteger('document_id');
                $table->string('old_status', 50)->nullable();
                $table->string('new_status', 50);
                $table->string('action', 50);
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->string('user_role', 100)->nullable();
                $table->text('reason')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['document_type', 'document_id'], 'idx_svl_document');
                $table->index(['user_id', 'created_at'],         'idx_svl_user_date');
                $table->index(['action', 'created_at'],           'idx_svl_action_date');
            });
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $old => $new) {
            DB::table('permissions')->where('name', $new)->update(['name' => $old, 'updated_at' => now()]);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Schema::dropIfExists('sales_validation_logs');
    }
};
