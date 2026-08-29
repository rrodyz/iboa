#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════════
# A3 ERP — Health check post-déploiement (lecture seule)
# ═══════════════════════════════════════════════════════════════════════════════
# Usage : ./deploy/health-check.sh
# Exit 0 = PASS, non-zero = FAIL.
#
# Ne crée AUCUNE donnée métier. Vérifie uniquement l'état de l'application,
# jamais son contenu (aucune commande/facture/OF n'est créé ou modifié).
# ═══════════════════════════════════════════════════════════════════════════════

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR" || { echo "[FAIL] Impossible d'accéder à $APP_DIR"; exit 1; }

FAILED=0
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
pass() { echo -e "${GREEN}[PASS]${NC} $*"; }
fail() { echo -e "${RED}[FAIL]${NC} $*"; FAILED=1; }
warnc() { echo -e "${YELLOW}[WARN]${NC} $*"; }

echo "═══════════════════════════════════════"
echo " A3 ERP — Health Check — $(date '+%Y-%m-%d %H:%M:%S')"
echo "═══════════════════════════════════════"

# ── 1. PHP répond ────────────────────────────────────────────────────────────
if php -v >/dev/null 2>&1; then
    pass "PHP répond ($(php -r 'echo PHP_VERSION;'))"
else
    fail "PHP ne répond pas"
fi

# ── 2. .env présent + APP_ENV/APP_DEBUG ──────────────────────────────────────
if [ -f .env ]; then
    pass ".env présent"
    grep -q "^APP_ENV=production" .env && pass "APP_ENV=production" || fail "APP_ENV n'est pas production"
    grep -q "^APP_DEBUG=false" .env && pass "APP_DEBUG=false" || fail "APP_DEBUG n'est pas false"
else
    fail ".env introuvable"
fi

# ── 3. Laravel boot ───────────────────────────────────────────────────────────
if php artisan --version >/dev/null 2>&1; then
    pass "Laravel boot OK ($(php artisan --version))"
else
    fail "Laravel ne démarre pas (php artisan --version a échoué)"
fi

# ── 4. Connexion base de données ─────────────────────────────────────────────
if php artisan db:show >/dev/null 2>&1; then
    pass "Connexion base de données OK"
else
    fail "Connexion base de données impossible (php artisan db:show)"
fi

# ── 5. Connexion Redis ────────────────────────────────────────────────────────
if php artisan tinker --execute="Illuminate\Support\Facades\Redis::ping();" >/dev/null 2>&1; then
    pass "Connexion Redis OK"
else
    fail "Connexion Redis impossible"
fi

# ── 6. Migration gate ──────────────────────────────────────────────────────────
if php artisan a3:check-pending-migrations >/dev/null 2>&1; then
    pass "Migration gate : 0 en attente"
else
    fail "Migration(s) en attente — voir : php artisan migrate:status --pending"
fi

# ── 7. Storage inscriptible ────────────────────────────────────────────────────
TESTFILE="storage/framework/cache/.health-check-$$"
if touch "$TESTFILE" 2>/dev/null; then
    rm -f "$TESTFILE"
    pass "storage/framework/cache inscriptible"
else
    fail "storage/framework/cache non inscriptible"
fi

# ── 8. Build frontend présent ──────────────────────────────────────────────────
if [ -f "public/build/manifest.json" ]; then
    pass "public/build/manifest.json présent"
else
    fail "public/build/manifest.json absent — build frontend manquant"
fi

# ── 9. Scheduler accessible ────────────────────────────────────────────────────
if php artisan schedule:list >/dev/null 2>&1; then
    pass "Scheduler accessible (php artisan schedule:list)"
else
    fail "Scheduler inaccessible"
fi

# ── 10. Queue (Redis driver attendu) ───────────────────────────────────────────
if grep -q "^QUEUE_CONNECTION=redis" .env 2>/dev/null; then
    pass "QUEUE_CONNECTION=redis (workers Supervisor attendus — vérifier supervisorctl status séparément)"
else
    warnc "QUEUE_CONNECTION n'est pas redis — vérifier si intentionnel (mode pilote sync ?)"
fi

echo "═══════════════════════════════════════"
if [ "$FAILED" -eq 0 ]; then
    echo -e "${GREEN}HEALTH CHECK : PASS${NC}"
    exit 0
else
    echo -e "${RED}HEALTH CHECK : FAIL${NC}"
    exit 1
fi
