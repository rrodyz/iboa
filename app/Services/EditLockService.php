<?php

namespace App\Services;

use App\Models\EditLock;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * [CONCURRENCE-MULTI-USER] Service de verrous pessimistes d'édition.
 *
 * Fonctionnement :
 *  - acquire()  : tente d'acquérir le verrou sur un document
 *  - release()  : libère le verrou (après save ou annulation)
 *  - refresh()  : renouvelle le TTL (ping JS toutes les 5 min)
 *  - check()    : consulte le verrou courant sans le modifier
 *  - purge()    : supprime tous les verrous expirés (schedulé)
 *
 * TTL par défaut : 15 minutes renouvelable par ping AJAX côté client.
 */
class EditLockService
{
    const TTL_MINUTES = 15;

    /**
     * Tente d'acquérir le verrou pour $user sur $model.
     *
     * Retourne :
     *  - EditLock  : verrou acquis (ou renouvelé si même user/session)
     *  - null      : verrou détenu par un autre user → accès refusé
     */
    public function acquire(Model $model, User $user): ?EditLock
    {
        return DB::transaction(function () use ($model, $user) {
            $existing = EditLock::where('lockable_type', $model->getMorphClass())
                ->where('lockable_id', $model->getKey())
                ->lockForUpdate()
                ->first();

            // Pas de verrou existant : on crée
            if (!$existing) {
                return $this->createLock($model, $user);
            }

            // Verrou expiré : on le remplace
            if ($existing->isExpired()) {
                $existing->delete();
                return $this->createLock($model, $user);
            }

            // Verrou appartient au même utilisateur ou à la même session : on renouvelle
            if ($existing->isOwnedBy($user) || $existing->isOwnedByCurrentSession()) {
                $existing->update([
                    'user_id'    => $user->id,
                    'session_id' => session()->getId(),
                    'expires_at' => now()->addMinutes(self::TTL_MINUTES),
                ]);
                return $existing->fresh('user');
            }

            // Verrou détenu par un autre utilisateur actif → refus
            return null;
        });
    }

    /**
     * Libère le verrou d'un utilisateur sur un document.
     * Ne fait rien si l'utilisateur ne détient pas le verrou.
     */
    public function release(Model $model, User $user): void
    {
        EditLock::where('lockable_type', $model->getMorphClass())
            ->where('lockable_id', $model->getKey())
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * Libère le verrou par session (utile quand la session change ou se ferme).
     */
    public function releaseBySession(Model $model, string $sessionId): void
    {
        EditLock::where('lockable_type', $model->getMorphClass())
            ->where('lockable_id', $model->getKey())
            ->where('session_id', $sessionId)
            ->delete();
    }

    /**
     * Renouvelle le TTL du verrou (appelé par le ping AJAX toutes les 5 min).
     * Retourne false si le verrou n'existe plus ou appartient à un autre user.
     */
    public function refresh(Model $model, User $user): bool
    {
        $updated = EditLock::where('lockable_type', $model->getMorphClass())
            ->where('lockable_id', $model->getKey())
            ->where('user_id', $user->id)
            ->update(['expires_at' => now()->addMinutes(self::TTL_MINUTES)]);

        return $updated > 0;
    }

    /**
     * Consulte le verrou actif sans le modifier.
     * Retourne null si aucun verrou actif.
     */
    public function check(Model $model): ?EditLock
    {
        $lock = EditLock::with('user')
            ->where('lockable_type', $model->getMorphClass())
            ->where('lockable_id', $model->getKey())
            ->first();

        if (!$lock) {
            return null;
        }

        if ($lock->isExpired()) {
            $lock->delete();
            return null;
        }

        return $lock;
    }

    /**
     * Vérifie si un document est verrouillé par un autre utilisateur.
     */
    public function isLockedByOther(Model $model, User $user): bool
    {
        $lock = $this->check($model);
        return $lock && !$lock->isOwnedBy($user);
    }

    /**
     * Force la libération d'un verrou (admin uniquement).
     */
    public function forceRelease(Model $model): void
    {
        EditLock::where('lockable_type', $model->getMorphClass())
            ->where('lockable_id', $model->getKey())
            ->delete();
    }

    /**
     * Supprime tous les verrous expirés.
     * Appelé par le scheduler (AutomationDaily ou commande dédiée).
     */
    public function purgeExpired(): int
    {
        return EditLock::expired()->delete();
    }

    /**
     * Liste tous les verrous actifs (pour le monitoring admin).
     */
    public function activeLocksFor(string $morphClass): \Illuminate\Support\Collection
    {
        return EditLock::with('user')
            ->where('lockable_type', $morphClass)
            ->active()
            ->orderBy('expires_at')
            ->get();
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function createLock(Model $model, User $user): EditLock
    {
        return EditLock::create([
            'lockable_type' => $model->getMorphClass(),
            'lockable_id'   => $model->getKey(),
            'user_id'       => $user->id,
            'session_id'    => session()->getId(),
            'locked_at'     => now(),
            'expires_at'    => now()->addMinutes(self::TTL_MINUTES),
        ]);
    }
}
