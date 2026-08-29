#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════════
# A3 ERP — Smoke test post-déploiement (non-destructif)
# ═══════════════════════════════════════════════════════════════════════════════
# Usage : ./deploy/smoke-test.sh https://erp.exemple.bf
#
# Ne crée AUCUNE donnée métier (aucune facture/OF/commande). Vérifie
# uniquement l'accessibilité HTTP des pages PUBLIQUES (avant authentification)
# — la quasi-totalité de l'application A3 exige une session authentifiée, ce
# que curl seul ne peut pas simuler proprement (CSRF + session). Les pages
# authentifiées sont donc listées ci-dessous pour un CONTRÔLE MANUEL après
# connexion, pas testées automatiquement ici.
# ═══════════════════════════════════════════════════════════════════════════════

BASE_URL="${1:-}"
[ -n "$BASE_URL" ] || { echo "Usage: $0 https://votre-domaine.com"; exit 2; }
BASE_URL="${BASE_URL%/}"

RED='\033[0;31m'; GREEN='\033[0;32m'; NC='\033[0m'
FAILED=0

check() {
    local path="$1" expected="$2"
    local code
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "${BASE_URL}${path}")"
    if [ "$code" = "$expected" ]; then
        echo -e "${GREEN}[PASS]${NC} $path -> $code"
    else
        echo -e "${RED}[FAIL]${NC} $path -> $code (attendu $expected)"
        FAILED=1
    fi
}

echo "═══════════════════════════════════════"
echo " A3 ERP — Smoke test public — $BASE_URL"
echo "═══════════════════════════════════════"

check "/" "302"       # redirige vers /login si non authentifié
check "/login" "200"

echo "═══════════════════════════════════════"
if [ "$FAILED" -eq 0 ]; then
    echo -e "${GREEN}SMOKE TEST (public) : PASS${NC}"
else
    echo -e "${RED}SMOKE TEST (public) : FAIL${NC}"
fi

cat <<'EOF'

── CONTRÔLE MANUEL REQUIS (routes authentifiées) ──────────────────────────
curl seul ne peut pas valider ces pages (session + CSRF). Se connecter avec
un compte de test et vérifier visuellement le chargement sans erreur 500 :

  Générique         : /dashboard, /clients, /articles, /stocks, /ventes,
                       /achats
  Production        : /production/dashboard, /production/orders,
                       /production/orders/mto, /production/orders/mts,
                       /production/mrp, /production/planning,
                       /production/suivis, /production/bom,
                       /production/routings
  Qualité           : /qualite/indicateurs, /qualite/inspections,
                       /qualite/liberations
  Documents         : génération PDF facture, un export (Excel/CSV)
  Infrastructure    : php artisan schedule:run (à blanc), envoi mail de test

Points d'attention spécifiques PROD-01 :
  - /production/planning doit afficher le bandeau conflits sans erreur SQL
    (dépend de la migration production_calendar_exceptions — voir
    deploy/health-check.sh, vérification "Migration gate").
  - /production/orders/mto doit afficher les commandes MTO avec leur statut
    financier correct (VISIBLE/BLOCKED/eligible) sans erreur.
EOF
