// [A3-UI-V2 — REACT-01B] Helper d'autorité UX — masque/affiche uniquement.
// Les routes serveur restent protégées par Gate/middleware permission:*,
// indépendamment de ce que ce hook retourne. Ne JAMAIS considérer can()
// comme une protection de sécurité.
import { usePage } from '@inertiajs/react';

export default function useAuth() {
    const { auth } = usePage().props;

    const can = (permission) => {
        if (auth?.is_super_admin) {
            return true;
        }
        return Boolean(auth?.permissions?.includes(permission));
    };

    return { user: auth?.user ?? null, isSuperAdmin: Boolean(auth?.is_super_admin), permissions: auth?.permissions ?? [], can };
}
